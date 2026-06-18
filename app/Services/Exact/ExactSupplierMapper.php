<?php

namespace App\Services\Exact;

use App\Models\Supplier;

class ExactSupplierMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toExactAccount(Supplier $supplier): array
    {
        $payload = [
            'Name' => $supplier->name,
            'IsSupplier' => true,
            'AddressLine1' => $supplier->address,
            'SearchCode' => self::searchCode($supplier),
            'Email' => $supplier->email,
            'Phone' => $supplier->phone,
            'VATNumber' => $supplier->vat_number,
            'ChamberOfCommerce' => $supplier->kvk_number,
            'Language' => 'NL',
        ];

        if ($purchaseVatCode = self::purchaseVatCode()) {
            $payload['PurchaseVATCode'] = $purchaseVatCode;
        }

        return array_filter(
            $payload,
            static fn (mixed $value): bool => filled($value),
        );
    }

    public static function searchCode(Supplier $supplier): string
    {
        $prefix = (string) config('exact.supplier.search_code_prefix', 'KOYLU-S');

        return sprintf('%s-%d', $prefix, $supplier->id);
    }

    public static function supplierIdFromSearchCode(?string $searchCode): ?int
    {
        if (! filled($searchCode)) {
            return null;
        }

        $prefix = preg_quote((string) config('exact.supplier.search_code_prefix', 'KOYLU-S'), '/');

        if (! preg_match('/^'.$prefix.'-(\d+)$/', $searchCode, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function purchaseVatCode(): ?string
    {
        return config('exact.supplier.vat_code');
    }
}
