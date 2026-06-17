<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('ensures product storage directories exist on boot', function () {
    $directories = [
        storage_path('app/public/products'),
        storage_path('app/public/livewire-tmp'),
    ];

    foreach ($directories as $directory) {
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }
    }

    (new AppServiceProvider(app()))->boot();

    foreach ($directories as $directory) {
        expect(is_dir($directory))->toBeTrue();
    }
});

it('builds a public url for a stored product image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('products/test.jpg', 'image-data');

    $product = Product::factory()->create([
        'image_path' => 'products/test.jpg',
    ]);

    expect($product->imageUrl())->toBe(Storage::disk('public')->url('products/test.jpg'));
});

it('stores an uploaded product image on the public disk', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($admin)
        ->test(EditProduct::class, ['record' => $product->id])
        ->fillForm([
            'image_path' => UploadedFile::fake()->image('kipfilet.jpg'),
        ])
        ->call('save')
        ->assertNotified();

    $product->refresh();

    expect($product->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($product->image_path);
});
