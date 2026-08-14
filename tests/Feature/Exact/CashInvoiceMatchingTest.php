<?php

use App\Enums\PaymentMethod;
use App\Jobs\ProcessCashPaidInvoice;
use App\Mail\InvoicePaidMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactCashEntryService;
use App\Services\Exact\ExactInvoiceSyncService;
use App\Services\Exact\ExactMatchService;
use App\Services\Exact\ExactOnlineClient;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function bookedCashInvoice(): Invoice
{
    $customer = Customer::factory()->create(['email' => 'klant@example.com']);
    $order = Order::factory()->for($customer)->create();

    return Invoice::factory()->for($order)->create([
        'payment_method' => PaymentMethod::CASH,
        'total_amount' => 133.12,
        'paid_amount' => 133.12,
        'exact_invoice_id' => 'ffffffff-0000-1111-2222-333333333333',
        'exact_document_number' => '26700006',
        'exact_cash_entry_id' => '11111111-2222-3333-4444-555555555555',
        'pdf_path' => 'facturen/test.pdf',
    ]);
}

function runCashJob(Invoice $invoice, ExactMatchService $matchService): void
{
    Mail::fake();

    $client = Mockery::mock(ExactOnlineClient::class);
    $client->shouldReceive('isConnected')->andReturnTrue();

    $cashEntryService = Mockery::mock(ExactCashEntryService::class);
    $cashEntryService->shouldReceive('registerPayment')->once();

    app()->instance(ExactMatchService::class, $matchService);

    (new ProcessCashPaidInvoice($invoice))->handle(
        app(ExactInvoiceSyncService::class),
        $cashEntryService,
        $client,
        app(InvoiceService::class),
    );
}

it('lettert de contante betaling af zodra de kasboeking gemaakt is', function () {
    $invoice = bookedCashInvoice();

    $matchService = Mockery::mock(ExactMatchService::class);
    $matchService->shouldReceive('isEnabled')->andReturnTrue();
    $matchService->shouldReceive('matchCashPayment')->once()->andReturnTrue();

    runCashJob($invoice, $matchService);
});

it('slaat afletteren over als de schakelaar uit staat', function () {
    $invoice = bookedCashInvoice();

    $matchService = Mockery::mock(ExactMatchService::class);
    $matchService->shouldReceive('isEnabled')->andReturnFalse();
    $matchService->shouldNotReceive('matchCashPayment');

    runCashJob($invoice, $matchService);
});

it('laat de geboekte factuur intact als het afletteren mislukt', function () {
    $invoice = bookedCashInvoice();

    $matchService = Mockery::mock(ExactMatchService::class);
    $matchService->shouldReceive('isEnabled')->andReturnTrue();
    $matchService->shouldReceive('matchCashPayment')
        ->andThrow(new ExactApiException('XML-upload naar Exact mislukt (HTTP 500).'));

    runCashJob($invoice, $matchService);

    /** De boeking blijft staan; alleen het afletteren is als fout genoteerd. */
    expect($invoice->fresh())
        ->exact_invoice_id->not->toBeNull()
        ->exact_document_number->toBe('26700006')
        ->exact_sync_error->toBeNull()
        ->exact_matched_at->toBeNull()
        ->exact_match_error->toContain('HTTP 500');

    $this->assertDatabaseHas('exact_sync_logs', [
        'action' => 'match_cash_payment',
        'status' => 'failed',
    ]);

    /** En de klant krijgt zijn factuur gewoon. */
    Mail::assertSent(InvoicePaidMail::class);
});
