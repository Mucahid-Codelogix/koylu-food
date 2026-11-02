<?php

use App\Models\Customer;
use App\Models\ExactToken;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{customer: Customer, supplier: Supplier, product: Product, invoice: Invoice}
 */
function linkedToTestAdministration(): array
{
    Queue::fake();

    $customer = Customer::factory()->create([
        'exact_account_id' => 'aaaaaaaa-0000-0000-0000-000000000001',
        'exact_synced_at' => now(),
        'exact_sync_error' => 'Oude fout',
    ]);

    $supplier = Supplier::factory()->create([
        'exact_account_id' => 'bbbbbbbb-0000-0000-0000-000000000002',
        'exact_synced_at' => now(),
    ]);

    $product = Product::factory()->create([
        'exact_article_code' => 'KOYLU-7',
        'exact_item_id' => 'cccccccc-0000-0000-0000-000000000003',
        'exact_synced_at' => now(),
    ]);

    $invoice = Invoice::factory()->create([
        'exact_invoice_id' => 'dddddddd-0000-0000-0000-000000000004',
        'exact_document_number' => '26700001',
        'exact_synced_at' => now(),
    ]);

    ExactToken::storeOrUpdate([
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addMinutes(10),
        'division' => 4480154,
        'division_name' => 'Koylu TEST',
    ]);

    Queue::fake();

    return compact('customer', 'supplier', 'product', 'invoice');
}

it('toont in een proefrun wat er gewist wordt zonder iets te wissen', function () {
    ['customer' => $customer, 'product' => $product] = linkedToTestAdministration();

    $this->artisan('exact:reset-connection', ['--dry-run' => true])
        ->expectsTable(['Onderdeel', 'Te wissen'], [
            ['Klanten', 1],
            ['Leveranciers', 1],
            ['Artikelen', 1],
            ['Exact-koppeling (token)', 1],
        ])
        ->expectsOutputToContain('Proefrun')
        ->assertSuccessful();

    expect($customer->fresh()->exact_account_id)->not->toBeNull()
        ->and($product->fresh()->exact_item_id)->not->toBeNull()
        ->and(ExactToken::query()->count())->toBe(1);
});

it('wist de koppeling en de Exact-ID\'s van klanten, leveranciers en artikelen', function () {
    [
        'customer' => $customer,
        'supplier' => $supplier,
        'product' => $product,
    ] = linkedToTestAdministration();

    $this->artisan('exact:reset-connection', ['--force' => true])->assertSuccessful();

    expect($customer->fresh())
        ->exact_account_id->toBeNull()
        ->exact_synced_at->toBeNull()
        ->exact_sync_error->toBeNull()
        ->and($supplier->fresh())
        ->exact_account_id->toBeNull()
        ->exact_synced_at->toBeNull()
        ->and($product->fresh())
        ->exact_item_id->toBeNull()
        ->exact_synced_at->toBeNull()
        ->and(ExactToken::query()->count())->toBe(0);
});

it('laat artikelcodes en facturen staan', function () {
    ['product' => $product, 'invoice' => $invoice] = linkedToTestAdministration();

    $this->artisan('exact:reset-connection', ['--force' => true])->assertSuccessful();

    expect($product->fresh()->exact_article_code)->toBe('KOYLU-7')
        ->and($invoice->fresh())
        ->exact_invoice_id->toBe('dddddddd-0000-0000-0000-000000000004')
        ->exact_document_number->toBe('26700001')
        ->exact_synced_at->not->toBeNull();
});

it('start geen syncs naar Exact en laat de wijzigingsdatum van records staan', function () {
    ['customer' => $customer] = linkedToTestAdministration();
    $originalUpdatedAt = $customer->fresh()->updated_at;

    $this->travel(1)->days();

    $this->artisan('exact:reset-connection', ['--force' => true])->assertSuccessful();

    Queue::assertNothingPushed();

    expect($customer->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

it('wist niets als de bevestiging wordt geweigerd', function () {
    ['customer' => $customer] = linkedToTestAdministration();

    $this->artisan('exact:reset-connection')
        ->expectsConfirmation('Exact-koppeling en alle Exact-ID\'s hierboven wissen?', 'no')
        ->expectsOutputToContain('Afgebroken')
        ->assertFailed();

    expect($customer->fresh()->exact_account_id)->not->toBeNull()
        ->and(ExactToken::query()->count())->toBe(1);
});
