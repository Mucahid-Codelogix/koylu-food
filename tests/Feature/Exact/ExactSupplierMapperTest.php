<?php

use App\Models\Supplier;
use App\Services\Exact\ExactSupplierMapper;

it('maps supplier fields to an exact creditor account payload', function () {
    config([
        'exact.supplier.search_code_prefix' => 'KOYLU-S',
        'exact.supplier.vat_code' => 'I2',
    ]);

    $supplier = Supplier::factory()->make([
        'id' => 12,
        'name' => 'Pluimvee Groothandel',
        'email' => 'inkoop@pluimvee.nl',
        'phone' => '050-7654321',
        'address' => 'Industrieweg 5, 9711AA Groningen',
        'vat_number' => 'NL998877665B01',
        'kvk_number' => '12345678',
    ]);

    expect(ExactSupplierMapper::toExactAccount($supplier))->toBe([
        'Name' => 'Pluimvee Groothandel',
        'IsSupplier' => true,
        'AddressLine1' => 'Industrieweg 5, 9711AA Groningen',
        'SearchCode' => 'KOYLU-S-12',
        'Email' => 'inkoop@pluimvee.nl',
        'Phone' => '050-7654321',
        'VATNumber' => 'NL998877665B01',
        'ChamberOfCommerce' => '12345678',
        'Language' => 'NL',
        'PurchaseVATCode' => 'I2',
    ]);
});

it('builds a stable supplier search code from the supplier id', function () {
    config(['exact.supplier.search_code_prefix' => 'KOYLU-S']);

    $supplier = Supplier::factory()->make(['id' => 8]);

    expect(ExactSupplierMapper::searchCode($supplier))->toBe('KOYLU-S-8');
});

it('parses supplier ids from exact search codes', function () {
    config(['exact.supplier.search_code_prefix' => 'KOYLU-S']);

    expect(ExactSupplierMapper::supplierIdFromSearchCode('KOYLU-S-8'))->toBe(8)
        ->and(ExactSupplierMapper::supplierIdFromSearchCode('KOYLU-8'))->toBeNull();
});

it('omits purchase vat code when it is not configured', function () {
    config(['exact.supplier.vat_code' => null]);

    $supplier = Supplier::factory()->make([
        'id' => 1,
        'name' => 'No VAT Config',
    ]);

    expect(ExactSupplierMapper::toExactAccount($supplier))
        ->not->toHaveKey('PurchaseVATCode');
});
