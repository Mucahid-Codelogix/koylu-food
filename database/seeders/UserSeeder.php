<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
        ]);

        // Drivers
        User::factory()->count(2)->create([
            'role' => UserRole::DRIVER,
        ]);

        // Customer users
        Customer::all()->each(function ($customer) {
            User::factory()->create([
                'customer_id' => $customer->id,
                'role' => UserRole::CUSTOMER,
                'email' => 'customer'.$customer->id.'@test.com',
            ]);
        });
    }
}
