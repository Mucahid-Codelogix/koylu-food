<?php

namespace App\Services\Exact;

use App\Models\Customer;
use Picqer\Financials\Exact\Account;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\Connection;

class ExactCustomerImportService
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
                'SearchCode',
                'SalesVATCode',
                'Blocked',
            ]);

            try {
                $accounts = $account->filterAsGenerator("Status eq 'C'", '', $select);
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
        $attributes = ExactAccountToCustomerMapper::toCustomerAttributes($exactAccount);
        $existing = $this->findExistingCustomer($exactAccount);

        if ($existing === null) {
            Customer::withoutEvents(function () use ($attributes): void {
                Customer::query()->create($attributes);
            });

            $result->created++;

            return;
        }

        $existing->updateQuietly($attributes);
        $result->updated++;
    }

    private function findExistingCustomer(Account $exactAccount): ?Customer
    {
        $accountId = (string) $exactAccount->ID;

        $byExactId = Customer::query()
            ->where('exact_account_id', $accountId)
            ->first();

        if ($byExactId !== null) {
            return $byExactId;
        }

        if ($customerId = ExactCustomerMapper::customerIdFromSearchCode($exactAccount->SearchCode ?? null)) {
            $bySearchCode = Customer::query()->find($customerId);

            if ($bySearchCode !== null) {
                return $bySearchCode;
            }
        }

        if (filled($exactAccount->Email)) {
            return Customer::query()
                ->where('email', (string) $exactAccount->Email)
                ->whereNull('exact_account_id')
                ->first();
        }

        return null;
    }
}
