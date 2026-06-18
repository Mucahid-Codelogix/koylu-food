<?php

use App\Models\Customer;
use App\Services\Exact\ExactCustomerMapper;

it('maps customer fields to an exact account payload', function () {
    config([
        'exact.customer.search_code_prefix' => 'KOYLU',
        'exact.customer.vat_codes.nl' => '2',
        'exact.customer.vat_codes.be' => 'BE21',
        'exact.customer.vat_codes.exempt' => '0',
    ]);

    $customer = Customer::factory()->make([
        'id' => 42,
        'company_name' => 'Restaurant Test',
        'contact_name' => 'Jan Jansen',
        'email' => 'test@example.com',
        'phone' => '050-1234567',
        'address' => 'Hoofdstraat 1',
        'postal_code' => '9711AA',
        'city' => 'Groningen',
        'country' => 'NL',
        'vat_number' => 'NL123456789B01',
        'exact_article_suffix' => '05',
        'is_vat_exempt' => false,
    ]);

    expect(ExactCustomerMapper::toExactAccount($customer))->toBe([
        'Name' => 'Restaurant Test',
        'Status' => 'C',
        'AddressLine1' => 'Hoofdstraat 1',
        'Postcode' => '9711AA',
        'City' => 'Groningen',
        'Country' => 'NL',
        'SearchCode' => 'KOYLU-42',
        'Email' => 'test@example.com',
        'Phone' => '050-1234567',
        'VATNumber' => 'NL123456789B01',
        'Language' => 'NL',
        'SalesVATCode' => '2',
    ]);
});

it('maps vat exempt customers to the exempt sales vat code', function () {
    config([
        'exact.customer.vat_codes.exempt' => 'V0',
    ]);

    $customer = Customer::factory()->make([
        'id' => 7,
        'company_name' => 'Belgian Buyer',
        'address' => 'Rue Example 10',
        'postal_code' => '1000',
        'city' => 'Brussels',
        'country' => 'BE',
        'exact_article_suffix' => '005',
        'is_vat_exempt' => true,
    ]);

    expect(ExactCustomerMapper::toExactAccount($customer))
        ->toHaveKey('SalesVATCode', 'V0')
        ->and(ExactCustomerMapper::countryCode($customer))->toBe('BE');
});

it('derives country from exact article suffix when country is missing', function () {
    $customer = Customer::factory()->make([
        'country' => null,
        'exact_article_suffix' => '005',
    ]);

    expect(ExactCustomerMapper::countryCode($customer))->toBe('BE');
});

it('builds a stable search code from the customer id', function () {
    config(['exact.customer.search_code_prefix' => 'KOYLU']);

    $customer = Customer::factory()->make(['id' => 15]);

    expect(ExactCustomerMapper::searchCode($customer))->toBe('KOYLU-15');
});

it('parses customer ids from exact search codes', function () {
    config(['exact.customer.search_code_prefix' => 'KOYLU']);

    expect(ExactCustomerMapper::customerIdFromSearchCode('KOYLU-15'))->toBe(15)
        ->and(ExactCustomerMapper::customerIdFromSearchCode('OTHER-15'))->toBeNull();
});

it('omits optional vat codes when they are not configured', function () {
    config([
        'exact.customer.vat_codes.nl' => null,
        'exact.customer.vat_codes.be' => null,
        'exact.customer.vat_codes.exempt' => null,
    ]);

    $customer = Customer::factory()->make([
        'id' => 1,
        'company_name' => 'No VAT Config',
        'address' => 'Straat 1',
        'postal_code' => '1234AB',
        'city' => 'Amsterdam',
        'country' => 'NL',
        'is_vat_exempt' => false,
    ]);

    expect(ExactCustomerMapper::toExactAccount($customer))
        ->not->toHaveKey('SalesVATCode');
});
