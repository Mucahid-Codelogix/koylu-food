<?php

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactMatchService;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactXmlClient;
use App\Services\Exact\ExactXmlMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * De opzoekingen in Exact leveren, in volgorde: het debiteurnummer, de
 * verkoopboeking en de kasboeking.
 */
function fakeExactLookups(): void
{
    $client = Mockery::mock(ExactOnlineClient::class);

    $client->shouldReceive('call')->andReturnValues([
        '715',
        (object) ['EntryNumber' => 26700006, 'ReportingYear' => 2026, 'ReportingPeriod' => 8, 'Journal' => '70'],
        (object) ['EntryNumber' => 26100001, 'FinancialYear' => 2026, 'FinancialPeriod' => 8, 'JournalCode' => '10'],
    ]);

    app()->instance(ExactOnlineClient::class, $client);
}

function cashInvoice(array $attributes = []): Invoice
{
    $customer = Customer::factory()->create(['exact_account_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']);
    $order = Order::factory()->for($customer)->create();

    return Invoice::factory()->for($order)->create(array_merge([
        'payment_method' => PaymentMethod::CASH,
        'total_amount' => 133.12,
        'paid_amount' => 133.12,
        'exact_document_number' => '26700006',
        'exact_invoice_id' => 'ffffffff-0000-1111-2222-333333333333',
        'exact_cash_entry_id' => '11111111-2222-3333-4444-555555555555',
    ], $attributes));
}

beforeEach(function () {
    config()->set('exact.cash.receivables_gl_account', '1300');
});

it('builds a matchset with the sales entry as plus and the cash entry as min', function () {
    $xml = app(ExactMatchService::class)->buildMatchSet(
        accountCode: '715',
        salesEntry: ['entry' => 26700006, 'year' => 2026, 'period' => 8, 'journal' => '70'],
        cashEntry: ['entry' => 26100001, 'year' => 2026, 'period' => 8, 'journal' => '10'],
        amount: 133.12,
    );

    expect($xml)
        ->toContain('<GLAccount code="1300" />')
        ->toContain('<Account code="715" />')
        ->toContain('finyear="2026" finperiod="8" journal="70" entry="26700006" amountdc="133.12"')
        ->toContain('finyear="2026" finperiod="8" journal="10" entry="26100001" amountdc="-133.12"');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('marks the invoice as matched when exact confirms the match', function () {
    fakeExactLookups();

    $xmlClient = Mockery::mock(ExactXmlClient::class);
    $xmlClient->shouldReceive('upload')->once()->with('MatchSets', Mockery::type('string'))
        ->andReturn([new ExactXmlMessage(ExactXmlMessage::TYPE_INFO, 'Rekening: 1300 - 2 Regels Afgeletterd.')]);
    $this->app->instance(ExactXmlClient::class, $xmlClient);

    $invoice = cashInvoice();

    expect(app(ExactMatchService::class)->matchCashPayment($invoice))->toBeTrue();

    expect($invoice->fresh())
        ->exact_matched_at->not->toBeNull()
        ->exact_match_error->toBeNull();
});

it('records the error and stays unmatched when exact reports a failure', function () {
    fakeExactLookups();

    $xmlClient = Mockery::mock(ExactXmlClient::class);
    $xmlClient->shouldReceive('upload')->once()->andReturn([new ExactXmlMessage(ExactXmlMessage::TYPE_ERROR, 'entry=26700006 Niet gevonden!')]);
    $this->app->instance(ExactXmlClient::class, $xmlClient);

    $invoice = cashInvoice();

    expect(app(ExactMatchService::class)->matchCashPayment($invoice))->toBeFalse();

    expect($invoice->fresh())
        ->exact_matched_at->toBeNull()
        ->exact_match_error->toContain('Niet gevonden!');

    $this->assertDatabaseHas('exact_sync_logs', [
        'action' => 'match_cash_payment',
        'status' => 'failed',
    ]);
});

it('refuses to match before the invoice and cash entry are booked', function () {
    $invoice = cashInvoice(['exact_cash_entry_id' => null]);

    expect(fn () => app(ExactMatchService::class)->matchCashPayment($invoice))
        ->toThrow(ExactApiException::class, 'Afletteren kan pas');
});

it('does nothing when the invoice is already matched', function () {
    $xmlClient = Mockery::mock(ExactXmlClient::class);
    $xmlClient->shouldNotReceive('upload');
    $this->app->instance(ExactXmlClient::class, $xmlClient);

    $invoice = cashInvoice(['exact_matched_at' => now()]);

    expect(app(ExactMatchService::class)->matchCashPayment($invoice))->toBeTrue();
});

it('is switched off by default', function () {
    config()->set('exact.match.auto_match_cash', false);

    expect(app(ExactMatchService::class)->isEnabled())->toBeFalse();
});
