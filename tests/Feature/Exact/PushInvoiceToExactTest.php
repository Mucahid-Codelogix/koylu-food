<?php

use App\Enums\InvoiceStatus;
use App\Jobs\PushInvoiceToExact;
use App\Models\Invoice;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactInvoicePushResult;
use App\Services\Exact\ExactInvoiceSyncService;
use App\Services\Exact\ExactOnlineClient;
use App\Services\InvoiceService;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('books the invoice in exact and generates documents with the exact number', function () {
    Storage::fake(UploadStorage::diskName());

    ['delivery' => $delivery] = makeInvoiceOrder([
        ['delivered' => 2, 'box_weight_kg' => 5, 'price_per_kg' => 10, 'vat_rate' => 21],
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactInvoiceSyncService::class, function ($mock): void {
        $mock->shouldReceive('push')
            ->once()
            ->andReturn(new ExactInvoicePushResult(
                invoiceId: '11111111-2222-3333-4444-555555555555',
                documentNumber: '20260099',
            ));
    });

    (new PushInvoiceToExact($invoice))->handle(
        app(ExactInvoiceSyncService::class),
        app(ExactOnlineClient::class),
        app(InvoiceService::class),
    );

    $invoice->refresh();

    expect($invoice->exact_invoice_id)->toBe('11111111-2222-3333-4444-555555555555')
        ->and($invoice->exact_document_number)->toBe('20260099')
        ->and($invoice->exact_synced_at)->not->toBeNull()
        ->and($invoice->exact_sync_error)->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::SENT)
        ->and($invoice->sent_at)->not->toBeNull()
        ->and($invoice->pdf_path)->not->toBeNull()
        ->and($invoice->ubl_path)->not->toBeNull()
        ->and($invoice->displayInvoiceNumber())->toBe('20260099');

    Storage::disk(UploadStorage::diskName())->assertExists($invoice->pdf_path);
    Storage::disk(UploadStorage::diskName())->assertExists($invoice->ubl_path);
});

it('skips pushing when the invoice is already booked in exact', function () {
    $invoice = Invoice::factory()->create([
        'exact_invoice_id' => '11111111-2222-3333-4444-555555555555',
        'exact_document_number' => '20260001',
        'exact_synced_at' => now(),
        'status' => InvoiceStatus::SENT,
    ]);

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactInvoiceSyncService::class, function ($mock): void {
        $mock->shouldNotReceive('push');
    });

    (new PushInvoiceToExact($invoice))->handle(
        app(ExactInvoiceSyncService::class),
        app(ExactOnlineClient::class),
        app(InvoiceService::class),
    );
});

it('records sync failures on the invoice', function () {
    ['delivery' => $delivery] = makeInvoiceOrder([
        ['delivered' => 1, 'box_weight_kg' => 5, 'price_per_kg' => 10, 'vat_rate' => 21],
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactInvoiceSyncService::class, function ($mock): void {
        $mock->shouldReceive('push')
            ->once()
            ->andThrow(new ExactApiException('Exact API fout'));
    });

    expect(fn () => (new PushInvoiceToExact($invoice))->handle(
        app(ExactInvoiceSyncService::class),
        app(ExactOnlineClient::class),
        app(InvoiceService::class),
    ))->toThrow(ExactApiException::class);

    $invoice->refresh();

    expect($invoice->exact_sync_error)->toBe('Exact API fout')
        ->and($invoice->status)->toBe(InvoiceStatus::CONCEPT);
});

it('records an error when exact is not connected', function () {
    $invoice = Invoice::factory()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(false);
    });

    $this->mock(ExactInvoiceSyncService::class, function ($mock): void {
        $mock->shouldNotReceive('push');
    });

    (new PushInvoiceToExact($invoice))->handle(
        app(ExactInvoiceSyncService::class),
        app(ExactOnlineClient::class),
        app(InvoiceService::class),
    );

    $invoice->refresh();

    expect($invoice->exact_sync_error)->toBe('Exact Online is niet gekoppeld.');
});

it('does not push twice when a cache lock is already held', function () {
    $invoice = Invoice::factory()->create();

    Cache::lock("exact-invoice-{$invoice->id}", 120)->get();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactInvoiceSyncService::class, function ($mock): void {
        $mock->shouldNotReceive('push');
    });

    (new PushInvoiceToExact($invoice))->handle(
        app(ExactInvoiceSyncService::class),
        app(ExactOnlineClient::class),
        app(InvoiceService::class),
    );

    Cache::lock("exact-invoice-{$invoice->id}", 120)->forceRelease();
});

it('shows the exact number in generated ubl after booking', function () {
    Storage::fake(UploadStorage::diskName());

    ['order' => $order, 'delivery' => $delivery] = makeInvoiceOrder([
        ['delivered' => 1, 'box_weight_kg' => 5, 'price_per_kg' => 10, 'vat_rate' => 21],
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);
    $invoice->update([
        'exact_document_number' => '20260123',
        'exact_invoice_id' => '11111111-2222-3333-4444-555555555555',
        'exact_synced_at' => now(),
    ]);

    app(InvoiceService::class)->generateUbl($invoice->fresh());

    $ubl = Storage::disk(UploadStorage::diskName())->get($invoice->fresh()->ubl_path);

    expect($ubl)->toContain('<cbc:ID>20260123</cbc:ID>');
});
