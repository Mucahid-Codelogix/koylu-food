<?php

use App\Services\Exact\ExactAccountToCustomerMapper;
use Picqer\Financials\Exact\Account;
use Picqer\Financials\Exact\Connection;

it('maps an exact account to customer attributes', function () {
    config(['exact.customer.vat_codes.exempt' => 'V0']);

    $account = new Account(new Connection);
    $account->ID = '11111111-2222-3333-4444-555555555555';
    $account->Name = 'Horeca Noord B.V.';
    $account->AddressLine1 = 'Straat 1';
    $account->Postcode = '9711AA';
    $account->City = 'Groningen';
    $account->Country = 'NL';
    $account->Email = 'inkoop@horeca.nl';
    $account->Phone = '050-1234567';
    $account->VATNumber = 'NL123456789B01';
    $account->SalesVATCode = '2';
    $account->Blocked = false;

    expect(ExactAccountToCustomerMapper::toCustomerAttributes($account))->toMatchArray([
        'company_name' => 'Horeca Noord B.V.',
        'email' => 'inkoop@horeca.nl',
        'phone' => '050-1234567',
        'address' => 'Straat 1',
        'postal_code' => '9711AA',
        'city' => 'Groningen',
        'country' => 'NL',
        'vat_number' => 'NL123456789B01',
        'exact_article_suffix' => '05',
        'is_vat_exempt' => false,
        'exact_account_id' => '11111111-2222-3333-4444-555555555555',
        'exact_sync_error' => null,
        'is_active' => true,
    ]);
});

it('marks belgian accounts with the be article suffix', function () {
    $account = new Account(new Connection);
    $account->ID = '22222222-2222-3333-4444-555555555555';
    $account->Name = 'Brussels Buyer';
    $account->Country = 'BE';
    $account->SalesVATCode = 'V0';

    config(['exact.customer.vat_codes.exempt' => 'V0']);

    expect(ExactAccountToCustomerMapper::toCustomerAttributes($account))
        ->toHaveKey('exact_article_suffix', '005')
        ->and(ExactAccountToCustomerMapper::isVatExempt($account))->toBeTrue();
});

it('uses placeholders for missing address fields', function () {
    $account = new Account(new Connection);
    $account->ID = '33333333-2222-3333-4444-555555555555';
    $account->Name = 'Minimaal';

    expect(ExactAccountToCustomerMapper::toCustomerAttributes($account))
        ->toHaveKey('address', 'Onbekend')
        ->toHaveKey('postal_code', '-')
        ->toHaveKey('city', 'Onbekend');
});
