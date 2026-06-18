<?php

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows admins to update a customer vat number', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create([
        'vat_number' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(EditCustomer::class, ['record' => $customer->id])
        ->fillForm([
            'vat_number' => 'NL123456782B01',
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    expect($customer->fresh()->vat_number)->toBe('NL123456782B01');
});
