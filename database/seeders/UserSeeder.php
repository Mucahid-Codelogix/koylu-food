<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@koylu.test'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'driver@koylu.test'],
            [
                'name' => 'Demo Chauffeur',
                'password' => Hash::make('password'),
                'role' => UserRole::DRIVER,
                'email_verified_at' => now(),
            ],
        );

        foreach ([
            ['email' => 'driver2@koylu.test', 'name' => 'Demo Chauffeur — laden bezig'],
            ['email' => 'driver3@koylu.test', 'name' => 'Demo Chauffeur — leveren'],
            ['email' => 'driver4@koylu.test', 'name' => 'Demo Chauffeur — deels geleverd'],
        ] as $driver) {
            User::query()->firstOrCreate(
                ['email' => $driver['email']],
                [
                    'name' => $driver['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::DRIVER,
                    'email_verified_at' => now(),
                ],
            );
        }

        $customers = Customer::query()->orderBy('id')->get();

        foreach ($customers as $index => $customer) {
            $number = $index + 1;

            User::query()->firstOrCreate(
                ['email' => "customer{$number}@koylu.test"],
                [
                    'name' => $customer->contact_name ?? $customer->company_name,
                    'customer_id' => $customer->id,
                    'password' => Hash::make('password'),
                    'role' => UserRole::CUSTOMER,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
