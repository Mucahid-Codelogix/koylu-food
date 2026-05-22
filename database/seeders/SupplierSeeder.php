<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::query()->firstOrCreate(
            ['name' => 'Pluimveehandel Noord'],
            [
                'contact_person' => 'Jan de Vries',
                'email' => 'inkoop@pluimveehandel-noord.nl',
                'phone' => '050-1234567',
                'vat_number' => 'NL123456789B01',
                'kvk_number' => '12345678',
                'address' => 'Industrieweg 12, Groningen',
                'is_active' => true,
            ],
        );

        Supplier::query()->firstOrCreate(
            ['name' => 'Kip Express BV'],
            [
                'contact_person' => 'Sandra Bakker',
                'email' => 'orders@kip-express.nl',
                'phone' => '020-9876543',
                'is_active' => true,
            ],
        );
    }
}
