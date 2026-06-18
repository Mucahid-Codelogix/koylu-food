<?php

namespace App\Services\Exact;

use Picqer\Financials\Exact\Account;

class ExactAccountToSupplierMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toSupplierAttributes(Account $account): array
    {
        return [
            'name' => (string) $account->Name,
            'email' => filled($account->Email) ? (string) $account->Email : null,
            'phone' => filled($account->Phone) ? (string) $account->Phone : null,
            'address' => self::formatAddress($account),
            'vat_number' => filled($account->VATNumber) ? (string) $account->VATNumber : null,
            'kvk_number' => filled($account->ChamberOfCommerce) ? (string) $account->ChamberOfCommerce : null,
            'exact_account_id' => (string) $account->ID,
            'exact_synced_at' => now(),
            'exact_sync_error' => null,
            'is_active' => ! (bool) ($account->Blocked ?? false),
        ];
    }

    private static function formatAddress(Account $account): ?string
    {
        $parts = array_filter([
            filled($account->AddressLine1) ? (string) $account->AddressLine1 : null,
            filled($account->Postcode) || filled($account->City)
                ? trim(((string) ($account->Postcode ?? '')).' '.((string) ($account->City ?? '')))
                : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
