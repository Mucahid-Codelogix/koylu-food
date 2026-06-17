<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('ensures product storage directories exist on boot when using local disk', function () {
    config([
        'filesystems.upload_disk' => 'public',
        'filesystems.disks.public.driver' => 'local',
        'filesystems.disks.public.root' => storage_path('app/public'),
    ]);

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

it('resolves the upload disk to public outside production', function () {
    expect(config('filesystems.upload_disk'))->toBe('public')
        ->and(UploadStorage::diskName())->toBe('public')
        ->and(UploadStorage::usesSpaces())->toBeFalse();
});

it('builds upload paths with upload env prefix on spaces', function () {
    config([
        'filesystems.upload_disk' => 'spaces',
        'filesystems.upload_env' => 'production',
    ]);

    expect(UploadStorage::directory('products'))->toBe('production/products');
});

it('builds a public url for a stored product image', function () {
    Storage::fake(UploadStorage::diskName());

    Storage::disk(UploadStorage::diskName())->put('products/test.jpg', 'image-data');

    $product = Product::factory()->create([
        'image_path' => 'products/test.jpg',
    ]);

    expect($product->imageUrl())->toBe(Storage::disk(UploadStorage::diskName())->url('products/test.jpg'));
});

it('stores an uploaded product image on the upload disk', function () {
    Storage::fake(UploadStorage::diskName());

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
    Storage::disk(UploadStorage::diskName())->assertExists($product->image_path);
});
