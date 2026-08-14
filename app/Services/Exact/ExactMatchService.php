<?php

namespace App\Services\Exact;

use App\Models\Invoice;
use Picqer\Financials\Exact\Account;
use Picqer\Financials\Exact\CashEntry;
use Picqer\Financials\Exact\Connection;
use Picqer\Financials\Exact\SalesEntry;

/**
 * Lettert een contante betaling af tegen de bijbehorende verkoopfactuur.
 *
 * De REST API van Exact kent hiervoor geen endpoint; dit loopt daarom via het
 * `MatchSets`-topic van de XML API. Zie EXACT_INTEGRATION_PLAN.md §8.
 */
class ExactMatchService
{
    public function __construct(
        private ExactOnlineClient $client,
        private ExactXmlClient $xmlClient,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('exact.match.auto_match_cash', false);
    }

    /**
     * @return bool Of de factuur na deze aanroep afgeletterd is.
     */
    public function matchCashPayment(Invoice $invoice): bool
    {
        if ($invoice->exact_matched_at !== null) {
            return true;
        }

        if (blank($invoice->exact_document_number) || blank($invoice->exact_cash_entry_id)) {
            throw new ExactApiException(
                'Afletteren kan pas als de factuur én de kasboeking in Exact staan.',
            );
        }

        $invoice->loadMissing('order.customer');

        $accountCode = $this->accountCode((string) $invoice->order->customer->exact_account_id);
        $salesEntry = $this->salesEntry((string) $invoice->exact_document_number);
        $cashEntry = $this->cashEntry((string) $invoice->exact_cash_entry_id);

        $amount = round((float) ($invoice->paid_amount ?? $invoice->total_amount), 2);

        $xml = $this->buildMatchSet(
            accountCode: $accountCode,
            salesEntry: $salesEntry,
            cashEntry: $cashEntry,
            amount: $amount,
        );

        $messages = $this->xmlClient->upload('MatchSets', $xml);

        $errors = array_filter($messages, fn (ExactXmlMessage $message): bool => $message->isError());

        if ($errors !== []) {
            $error = $this->describe($errors);

            $invoice->updateQuietly(['exact_match_error' => $error]);

            ExactSyncLogger::failed($invoice, 'match_cash_payment', $error);

            return false;
        }

        $invoice->updateQuietly([
            'exact_matched_at' => now(),
            'exact_match_error' => null,
        ]);

        ExactSyncLogger::success(
            $invoice,
            'match_cash_payment',
            /** Exact meldt zelf hoeveel regels zijn afgeletterd; dat bewaren we als bewijs. */
            trim(sprintf(
                'Contante betaling afgeletterd tegen factuur %s. %s',
                $invoice->exact_document_number,
                $this->describe($messages),
            )),
        );

        return true;
    }

    /**
     * @param  array{entry: int, year: int, period: int, journal: string}  $salesEntry
     * @param  array{entry: int, year: int, period: int, journal: string}  $cashEntry
     */
    public function buildMatchSet(string $accountCode, array $salesEntry, array $cashEntry, float $amount): string
    {
        $glAccount = (string) config('exact.cash.receivables_gl_account');

        return implode("\n", [
            '<?xml version="1.0" encoding="utf-8"?>',
            '<eExact xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="eExact-XML.xsd">',
            '  <MatchSets>',
            '    <MatchSet>',
            sprintf('      <GLAccount code="%s" />', $this->escape($glAccount)),
            sprintf('      <Account code="%s" />', $this->escape($accountCode)),
            '      <MatchLines>',
            $this->matchLine($salesEntry, $amount),
            $this->matchLine($cashEntry, -$amount),
            '      </MatchLines>',
            '    </MatchSet>',
            '  </MatchSets>',
            '</eExact>',
        ]);
    }

    /**
     * @param  array{entry: int, year: int, period: int, journal: string}  $entry
     */
    private function matchLine(array $entry, float $amount): string
    {
        return sprintf(
            '        <MatchLine finyear="%d" finperiod="%d" journal="%s" entry="%d" amountdc="%s" />',
            $entry['year'],
            $entry['period'],
            $this->escape($entry['journal']),
            $entry['entry'],
            number_format($amount, 2, '.', ''),
        );
    }

    private function accountCode(string $accountId): string
    {
        $code = $this->client->call(function (Connection $connection) use ($accountId): ?string {
            $results = (new Account($connection))->filter(
                "ID eq guid'{$accountId}'", '', 'Code', ['$top' => 1],
            );

            return isset($results[0]->Code) ? trim((string) $results[0]->Code) : null;
        });

        if (blank($code)) {
            throw new ExactApiException('Het debiteurnummer van deze klant is niet in Exact gevonden.');
        }

        return $code;
    }

    /**
     * @return array{entry: int, year: int, period: int, journal: string}
     */
    private function salesEntry(string $invoiceNumber): array
    {
        $entry = $this->client->call(function (Connection $connection) use ($invoiceNumber): ?object {
            $results = (new SalesEntry($connection))->filter(
                "InvoiceNumber eq {$invoiceNumber}",
                '',
                'EntryNumber,ReportingYear,ReportingPeriod,Journal',
                ['$top' => 1],
            );

            return $results[0] ?? null;
        });

        if ($entry === null) {
            throw new ExactApiException(sprintf(
                'De verkoopboeking van factuur %s is niet in Exact gevonden.',
                $invoiceNumber,
            ));
        }

        return [
            'entry' => (int) $entry->EntryNumber,
            'year' => (int) $entry->ReportingYear,
            'period' => (int) $entry->ReportingPeriod,
            'journal' => trim((string) $entry->Journal),
        ];
    }

    /**
     * @return array{entry: int, year: int, period: int, journal: string}
     */
    private function cashEntry(string $entryId): array
    {
        $entry = $this->client->call(function (Connection $connection) use ($entryId): ?object {
            $results = (new CashEntry($connection))->filter(
                "EntryID eq guid'{$entryId}'",
                '',
                'EntryNumber,FinancialYear,FinancialPeriod,JournalCode',
                ['$top' => 1],
            );

            return $results[0] ?? null;
        });

        if ($entry === null) {
            throw new ExactApiException('De kasboeking is niet in Exact gevonden.');
        }

        return [
            'entry' => (int) $entry->EntryNumber,
            'year' => (int) $entry->FinancialYear,
            'period' => (int) $entry->FinancialPeriod,
            'journal' => trim((string) $entry->JournalCode),
        ];
    }

    /**
     * @param  array<int, ExactXmlMessage>  $messages
     */
    private function describe(array $messages): string
    {
        return implode(' | ', array_map(
            fn (ExactXmlMessage $message): string => $message->description,
            $messages,
        ));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
