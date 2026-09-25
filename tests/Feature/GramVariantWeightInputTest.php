<?php

use App\Enums\ProductType;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\GramVariantsRelationManager;
use App\Filament\Resources\Products\Schemas\ProductGramVariantForm;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function gramVariantsManager(Product $product): Testable
{
    return Livewire::test(GramVariantsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ]);
}

function wholeChickenProduct(): Product
{
    return Product::factory()->create([
        'name' => 'Hele Kip',
        'product_type' => ProductType::WholeChicken,
    ]);
}

it('toont het getypte gewicht meteen in kilo onder het veld', function () {
    expect(ProductGramVariantForm::weightHelperText(1200))->toBe('= 1,200 kg')
        ->and(ProductGramVariantForm::weightHelperText(850))->toBe('= 0,850 kg');
});

it('waarschuwt als het gewicht in kilo in plaats van gram wordt ingevuld', function () {
    expect(ProductGramVariantForm::weightHelperText(11))
        ->toBe('= 0,011 kg — bedoelde je 11.000 g? Vul het gewicht in grammen in.');
});

it('legt de eenheid uit als er nog niets is ingevuld', function () {
    expect(ProductGramVariantForm::weightHelperText(null))->toBe('In hele grammen, bv. 1200 voor 1,2 kg')
        ->and(ProductGramVariantForm::weightHelperText(''))->toBe('In hele grammen, bv. 1200 voor 1,2 kg');
});

it('toont de kilo naast de grammen in de lijst met gramvarianten', function () {
    $this->actingAs(User::factory()->admin()->create());

    $product = wholeChickenProduct();
    ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
    ]);

    gramVariantsManager($product)
        ->assertSee('1200 g (1,200 kg)');
});

it('toont in de shop het doosgewicht in de keuzelijst met gramvarianten', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);

    $product = Product::factory()->create([
        'name' => 'Hele Kip',
        'product_type' => ProductType::WholeChicken,
    ]);

    ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12.35,
        'is_default' => true,
    ]);

    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => 6.5,
        'is_default' => true,
    ]);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->assertSee('1200 g · 12,35 kg/doos');
});
