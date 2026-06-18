<?php

use App\Services\Exact\ExactAccountToSupplierMapper;
use Picqer\Financials\Exact\Account;
use Picqer\Financials\Exact\Connection;

it('maps an exact creditor account to supplier attributes', function () {
    $account = new Account(new Connection);
    $account->ID = '11111111-2222-3333-4444-555555555555';
    $account->Name = 'Pluimvee Leverancier B.V.';
    $account->AddressLine1 = 'Industrieweg 5';
    $account->Postcode = '9711AA';
    $account->City = 'Groningen';
    $account->Email = 'inkoop@pluimvee.nl';
    $account->Phone = '050-7654321';
    $account->VATNumber = 'NL998877665B01';
    $account->ChamberOfCommerce = '12345678';
    $account->Blocked = false;

    expect(ExactAccountToSupplierMapper::toSupplierAttributes($account))->toMatchArray([
        'name' => 'Pluimvee Leverancier B.V.',
        'email' => 'inkoop@pluimvee.nl',
        'phone' => '050-7654321',
        'address' => 'Industrieweg 5, 9711AA Groningen',
        'vat_number' => 'NL998877665B01',
        'kvk_number' => '12345678',
        'exact_account_id' => '11111111-2222-3333-4444-555555555555',
        'exact_sync_error' => null,
        'is_active' => true,
    ]);
});

it('marks blocked exact accounts as inactive suppliers', function () {
    $account = new Account(new Connection);
    $account->ID = '22222222-2222-3333-4444-555555555555';
    $account->Name = 'Geblokkeerd';
    $account->Blocked = true;

    expect(ExactAccountToSupplierMapper::toSupplierAttributes($account))
        ->toHaveKey('is_active', false);
});

it('leaves address empty when exact has no address fields', function () {
    $account = new Account(new Connection);
    $account->ID = '33333333-2222-3333-4444-555555555555';
    $account->Name = 'Minimaal';

    expect(ExactAccountToSupplierMapper::toSupplierAttributes($account))
        ->toHaveKey('address', null);
});
