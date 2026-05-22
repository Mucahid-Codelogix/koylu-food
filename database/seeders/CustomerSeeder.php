<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $demoCustomers = [
            [
                'company_name' => 'Restaurant De Gouden Kip',
                'contact_name' => 'Mark Jansen',
                'email' => 'inkoop@goudenkip.nl',
                'city' => 'Groningen',
            ],
            [
                'company_name' => 'Slagerij Van Berg',
                'contact_name' => 'Els van Berg',
                'email' => 'bestellingen@slagerijvanberg.nl',
                'city' => 'Assen',
            ],
            [
                'company_name' => 'Horeca Groep Noord',
                'contact_name' => 'Peter Smit',
                'email' => 'orders@horecanord.nl',
                'city' => 'Leeuwarden',
            ],
            [
                'company_name' => 'Catering Fresh B.V.',
                'contact_name' => 'Lisa de Boer',
                'email' => 'inkoop@cateringfresh.nl',
                'city' => 'Zwolle',
            ],
            [
                'company_name' => 'Keuken van het Noorden',
                'contact_name' => 'Tom Visser',
                'email' => 'tom@keukennoord.nl',
                'city' => 'Emmen',
            ],
        ];

        foreach ($demoCustomers as $data) {
            Customer::query()->firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'phone' => '050-'.fake()->numerify('######'),
                    'address' => fake()->streetAddress(),
                    'postal_code' => fake()->postcode(),
                    'country' => 'NL',
                    'vat_number' => 'NL'.fake()->numerify('#########').'B01',
                ]),
            );
        }

        Customer::factory()->count(2)->create();
    }
}
