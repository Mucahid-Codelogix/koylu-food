<?php

use App\Support\VatNumber;

it('accepts a valid dutch vat number', function () {
    expect(VatNumber::isValidDutch('NL123456782B01'))->toBeTrue()
        ->and(VatNumber::forExact('NL 1234.567.82.B01', 'NL'))->toBe('NL123456782B01');
});

it('rejects fake dutch vat numbers used in demo data', function () {
    expect(VatNumber::isValidDutch('NL123456789B01'))->toBeFalse()
        ->and(VatNumber::forExact('NL123456789B01', 'NL'))->toBeNull();
});

it('returns null for empty vat numbers', function () {
    expect(VatNumber::forExact(null, 'NL'))->toBeNull()
        ->and(VatNumber::forExact('', 'NL'))->toBeNull();
});
