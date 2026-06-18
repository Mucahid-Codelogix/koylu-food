<?php

namespace App\Services\Exact;

use App\Models\Customer;
use App\Support\VatNumber;

class ExactCustomerMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toExactAccount(Customer $customer): array
    {
        $payload = [
            'Name' => $customer->company_name,
            'Status' => 'C',
            'AddressLine1' => $customer->address,
            'Postcode' => $customer->postal_code,
            'City' => $customer->city,
            'Country' => self::countryCode($customer),
            'SearchCode' => self::searchCode($customer),
            'Email' => $customer->email,
            'Phone' => $customer->phone,
            'VATNumber' => VatNumber::forExact($customer->vat_number, self::countryCode($customer)),
            'Language' => self::languageCode($customer),
        ];

        if ($salesVatCode = self::salesVatCode($customer)) {
            $payload['SalesVATCode'] = $salesVatCode;
        }

        return array_filter(
            $payload,
            static fn (mixed $value): bool => filled($value),
        );
    }

    public static function searchCode(Customer $customer): string
    {
        $prefix = (string) config('exact.customer.search_code_prefix', 'KOYLU');

        return sprintf('%s-%d', $prefix, $customer->id);
    }

    public static function customerIdFromSearchCode(?string $searchCode): ?int
    {
        if (! filled($searchCode)) {
            return null;
        }

        $prefix = preg_quote((string) config('exact.customer.search_code_prefix', 'KOYLU'), '/');

        if (! preg_match('/^'.$prefix.'-(\d+)$/', $searchCode, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function countryCode(Customer $customer): string
    {
        if (filled($customer->country)) {
            return strtoupper((string) $customer->country);
        }

        return match ($customer->exact_article_suffix) {
            '005' => 'BE',
            default => 'NL',
        };
    }

    public static function languageCode(Customer $customer): string
    {
        return 'NL';
    }

    public static function salesVatCode(Customer $customer): ?string
    {
        if ($customer->is_vat_exempt) {
            return config('exact.customer.vat_codes.exempt');
        }

        return match (self::countryCode($customer)) {
            'BE' => config('exact.customer.vat_codes.be'),
            default => config('exact.customer.vat_codes.nl'),
        };
    }
}
