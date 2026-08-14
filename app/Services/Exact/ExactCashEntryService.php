<?php

namespace App\Services\Exact;

use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\CashEntry;
use Picqer\Financials\Exact\Connection;
use Picqer\Financials\Exact\GLAccount;

/**
 * Schrijft een contante betaling weg als kasboeking in Exact.
 *
 * Let op: het afletteren van deze kasboeking tegen de verkoopfactuur kan niet
 * via de REST API. Zie EXACT_INTEGRATION_PLAN.md §8 — dat gebeurt (voorlopig)
 * handmatig in Exact of later via de XML API.
 */
class ExactCashEntryService
{
    public function __construct(
        private ExactOnlineClient $client,
    ) {}

    /**
     * Exact verwacht in een boekingsregel het GUID van de grootboekrekening,
     * niet het rekeningnummer. Het GUID onthouden we, want de rekening wijzigt niet.
     */
    private function glAccountId(string $code): string
    {
        return Cache::remember(
            "exact-gl-account-{$code}",
            now()->addDay(),
            function () use ($code): string {
                $id = $this->client->call(function (Connection $connection) use ($code): ?string {
                    $escapedCode = str_replace("'", "''", $code);

                    try {
                        $results = (new GLAccount($connection))
                            ->filter("Code eq '{$escapedCode}'", '', 'ID', ['$top' => 1]);
                    } catch (ApiException $exception) {
                        throw ExactApiException::fromPicqer($exception);
                    }

                    return isset($results[0]->ID) ? (string) $results[0]->ID : null;
                });

                if (blank($id)) {
                    throw new ExactApiException(sprintf(
                        'Grootboekrekening "%s" bestaat niet in Exact.',
                        $code,
                    ));
                }

                return $id;
            },
        );
    }

    public function registerPayment(Invoice $invoice): ?string
    {
        if (filled($invoice->exact_cash_entry_id)) {
            return $invoice->exact_cash_entry_id;
        }

        $journal = (string) config('exact.cash.journal');

        /**
         * Het kasdagboek is in Exact al aan de kasrekening gekoppeld; de
         * boekingsregel gaat daarom tegen de debiteurenrekening.
         */
        $glAccount = (string) config('exact.cash.receivables_gl_account');

        if (blank($journal) || blank($glAccount)) {
            ExactSyncLogger::failed(
                $invoice,
                'cash_entry',
                'Kasdagboek of debiteurenrekening is niet ingesteld; kasboeking overgeslagen.',
            );

            return null;
        }

        $invoice->loadMissing('order.customer');
        $accountId = $invoice->order->customer->exact_account_id;

        if (blank($accountId)) {
            throw new ExactApiException('De klant heeft geen Exact-debiteur; kasboeking niet mogelijk.');
        }

        $glAccountId = $this->glAccountId($glAccount);

        $entryId = $this->client->call(function (Connection $connection) use ($invoice, $journal, $glAccountId, $accountId): string {
            $cashEntry = new CashEntry($connection);
            $cashEntry->JournalCode = $journal;

            $cashEntry->CashEntryLines = [[
                'Account' => (string) $accountId,
                'AmountFC' => (float) ($invoice->paid_amount ?? $invoice->total_amount),
                'Date' => ($invoice->paid_at ?? now())->format('Y-m-d'),
                'Description' => sprintf('Contante betaling factuur %s', $invoice->displayInvoiceNumber()),
                'GLAccount' => $glAccountId,
                'OurRef' => $invoice->exact_document_number ?? $invoice->invoice_number,
            ]];

            try {
                $cashEntry->save();
            } catch (ApiException $exception) {
                throw ExactApiException::fromPicqer($exception);
            }

            if (! filled($cashEntry->EntryID)) {
                throw new ExactApiException('Exact heeft geen kasboeking-ID teruggegeven.');
            }

            return (string) $cashEntry->EntryID;
        });

        $invoice->updateQuietly([
            'exact_cash_entry_id' => $entryId,
            'exact_cash_entry_at' => now(),
        ]);

        ExactSyncLogger::success(
            $invoice,
            'cash_entry',
            sprintf('Kasboeking %s aangemaakt voor contante betaling.', $entryId),
        );

        return $entryId;
    }
}
