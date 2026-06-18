<?php

namespace App\Services\Exact;

use Picqer\Financials\Exact\Account;

class ExactAccountToCustomerMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toCustomerAttributes(Account $account): array
    {
        $country = strtoupper((string) ($account->Country ?: 'NL'));
        $syncedAt = now();

        return [
            'company_name' => (string) $account->Name,
            'email' => filled($account->Email) ? (string) $account->Email : null,
            'phone' => filled($account->Phone) ? (string) $account->Phone : null,
            'address' => filled($account->AddressLine1) ? (string) $account->AddressLine1 : 'Onbekend',
            'postal_code' => filled($account->Postcode) ? (string) $account->Postcode : '-',
            'city' => filled($account->City) ? (string) $account->City : 'Onbekend',
            'country' => $country,
            'vat_number' => filled($account->VATNumber) ? (string) $account->VATNumber : null,
            'exact_article_suffix' => $country === 'BE' ? '005' : '05',
            'is_vat_exempt' => self::isVatExempt($account),
            'exact_account_id' => (string) $account->ID,
            'exact_synced_at' => $syncedAt,
            'exact_sync_error' => null,
            'is_active' => ! (bool) ($account->Blocked ?? false),
        ];
    }

    public static function isVatExempt(Account $account): bool
    {
        $exemptCode = config('exact.customer.vat_codes.exempt');

        if (! filled($exemptCode)) {
            return false;
        }

        return (string) ($account->SalesVATCode ?? '') === (string) $exemptCode;
    }
}
