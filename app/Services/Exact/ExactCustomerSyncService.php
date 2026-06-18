<?php

namespace App\Services\Exact;

use App\Models\Customer;
use Picqer\Financials\Exact\Account;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\Connection;

class ExactCustomerSyncService
{
    public function __construct(private ExactOnlineClient $client) {}

    public function sync(Customer $customer): string
    {
        return $this->client->call(function (Connection $connection) use ($customer): string {
            $payload = ExactCustomerMapper::toExactAccount($customer);
            $account = new Account($connection);

            if (filled($customer->exact_account_id)) {
                return $this->updateAccount($account, (string) $customer->exact_account_id, $payload);
            }

            $existing = $this->findBySearchCode($account, ExactCustomerMapper::searchCode($customer));

            if ($existing !== null) {
                return $this->updateAccount($account, $existing, $payload);
            }

            return $this->createAccount($account, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createAccount(Account $account, array $payload): string
    {
        foreach ($payload as $field => $value) {
            $account->{$field} = $value;
        }

        try {
            $account->save();
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        if (! filled($account->ID)) {
            throw new ExactApiException('Exact heeft geen account-ID teruggegeven na aanmaken.');
        }

        return (string) $account->ID;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateAccount(Account $account, string $accountId, array $payload): string
    {
        $account->ID = $accountId;

        foreach ($payload as $field => $value) {
            if ($field === 'SearchCode') {
                continue;
            }

            $account->{$field} = $value;
        }

        try {
            $account->update();
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        return $accountId;
    }

    private function findBySearchCode(Account $account, string $searchCode): ?string
    {
        $escapedSearchCode = str_replace("'", "''", $searchCode);

        try {
            $results = $account->filter("SearchCode eq '{$escapedSearchCode}'", '', 'ID', ['$top' => 1]);
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        if ($results === [] || ! isset($results[0]->ID)) {
            return null;
        }

        return (string) $results[0]->ID;
    }
}
