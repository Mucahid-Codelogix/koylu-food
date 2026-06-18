<?php

namespace App\Services\Exact;

use App\Models\Supplier;
use Picqer\Financials\Exact\Account;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\Connection;

class ExactSupplierImportService
{
    public function __construct(private ExactOnlineClient $client) {}

    public function import(): ExactCustomerImportResult
    {
        return $this->client->call(function (Connection $connection): ExactCustomerImportResult {
            $result = new ExactCustomerImportResult;
            $account = new Account($connection);

            $select = implode(',', [
                'ID',
                'Name',
                'AddressLine1',
                'Postcode',
                'City',
                'Country',
                'Email',
                'Phone',
                'VATNumber',
                'ChamberOfCommerce',
                'SearchCode',
                'PurchaseVATCode',
                'Blocked',
            ]);

            try {
                $accounts = $account->filterAsGenerator('IsSupplier eq true', '', $select);
            } catch (ApiException $exception) {
                throw ExactApiException::fromPicqer($exception);
            }

            foreach ($accounts as $exactAccount) {
                if (! filled($exactAccount->ID) || ! filled($exactAccount->Name)) {
                    $result->skipped++;

                    continue;
                }

                $this->importAccount($exactAccount, $result);
            }

            return $result;
        });
    }

    private function importAccount(Account $exactAccount, ExactCustomerImportResult $result): void
    {
        $attributes = ExactAccountToSupplierMapper::toSupplierAttributes($exactAccount);
        $existing = $this->findExistingSupplier($exactAccount);

        if ($existing === null) {
            Supplier::withoutEvents(function () use ($attributes): void {
                Supplier::query()->create($attributes);
            });

            $result->created++;

            return;
        }

        $existing->updateQuietly($attributes);
        $result->updated++;
    }

    private function findExistingSupplier(Account $exactAccount): ?Supplier
    {
        $accountId = (string) $exactAccount->ID;

        $byExactId = Supplier::query()
            ->where('exact_account_id', $accountId)
            ->first();

        if ($byExactId !== null) {
            return $byExactId;
        }

        if ($supplierId = ExactSupplierMapper::supplierIdFromSearchCode($exactAccount->SearchCode ?? null)) {
            $bySearchCode = Supplier::query()->find($supplierId);

            if ($bySearchCode !== null) {
                return $bySearchCode;
            }
        }

        if (filled($exactAccount->Email)) {
            return Supplier::query()
                ->where('email', (string) $exactAccount->Email)
                ->whereNull('exact_account_id')
                ->first();
        }

        if (filled($exactAccount->VATNumber)) {
            return Supplier::query()
                ->where('vat_number', (string) $exactAccount->VATNumber)
                ->whereNull('exact_account_id')
                ->first();
        }

        return null;
    }
}
