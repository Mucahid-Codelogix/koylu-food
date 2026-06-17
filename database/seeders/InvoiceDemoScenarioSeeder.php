<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use Database\Seeders\Support\DemoInvoiceScenarioBuilder;
use Illuminate\Database\Seeder;

class InvoiceDemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $builder = app(DemoInvoiceScenarioBuilder::class);

        $partialCustomer = $this->customer(
            email: 'demo-factuur-deel@koylu.test',
            companyName: 'Demo Factuur — Deellevering',
            contactName: 'Sanne de Vries',
        );

        $builder->create(
            customer: $partialCustomer,
            orderNumber: 'DEMO-INV-PARTIAL',
            notes: 'Demo: deellevering (64,90 + 13,63 btw 21% = 78,53). Regel 3 niet geleverd.',
            lineSpecs: [
                [
                    'product_name' => 'Kipfilet blok',
                    'unit' => 'Doos 2,5 kg',
                    'quantity' => 4,
                    'box_weight_kg' => 2.5,
                    'price_per_kg' => 5.02,
                    'vat_rate' => 21,
                    'delivered' => 4,
                ],
                [
                    'product_name' => 'Kippendijen blok',
                    'unit' => 'Doos 2 kg',
                    'quantity' => 3,
                    'box_weight_kg' => 2,
                    'price_per_kg' => 4.90,
                    'vat_rate' => 21,
                    'delivered' => 1.5,
                ],
                [
                    'product_name' => 'Kippenvleugels blok',
                    'unit' => 'Doos 3 kg',
                    'quantity' => 2,
                    'box_weight_kg' => 3,
                    'price_per_kg' => 7.25,
                    'vat_rate' => 21,
                    'delivered' => 0,
                    'missed_reason' => 'Niet op voorraad',
                ],
            ],
        );

        $mixedCustomer = $this->customer(
            email: 'demo-factuur-mixed@koylu.test',
            companyName: 'Demo Factuur — Gemengde BTW',
            contactName: 'Ruben Bakker',
        );

        $builder->create(
            customer: $mixedCustomer,
            orderNumber: 'DEMO-INV-MIXED',
            notes: 'Demo: gemengde btw 9% + 21% (150,00 + 19,50 = 169,50).',
            lineSpecs: [
                [
                    'product_name' => 'Vers product (laag)',
                    'unit' => 'Doos 5 kg',
                    'quantity' => 2,
                    'box_weight_kg' => 5,
                    'price_per_kg' => 10,
                    'vat_rate' => 9,
                    'delivered' => 2,
                ],
                [
                    'product_name' => 'Vers product (hoog)',
                    'unit' => 'Doos 5 kg',
                    'quantity' => 1,
                    'box_weight_kg' => 5,
                    'price_per_kg' => 10,
                    'vat_rate' => 21,
                    'delivered' => 1,
                ],
            ],
            deliveryStatus: DeliveryStatus::DELIVERED,
        );

        $exemptCustomer = $this->customer(
            email: 'demo-factuur-vrij@koylu.test',
            companyName: 'Demo Factuur — BTW-vrij (BE)',
            contactName: 'Marie Dubois',
            isVatExempt: true,
            country: 'BE',
        );

        $builder->create(
            customer: $exemptCustomer,
            orderNumber: 'DEMO-INV-VRIJ',
            notes: 'Demo: btw-vrijgestelde klant (64,90 + 0,00 = 64,90). Zelfde levering als deellevering.',
            lineSpecs: [
                [
                    'product_name' => 'Kipfilet blok',
                    'unit' => 'Doos 2,5 kg',
                    'quantity' => 4,
                    'box_weight_kg' => 2.5,
                    'price_per_kg' => 5.02,
                    'vat_rate' => 21,
                    'delivered' => 4,
                ],
                [
                    'product_name' => 'Kippendijen blok',
                    'unit' => 'Doos 2 kg',
                    'quantity' => 3,
                    'box_weight_kg' => 2,
                    'price_per_kg' => 4.90,
                    'vat_rate' => 21,
                    'delivered' => 1.5,
                ],
                [
                    'product_name' => 'Kippenvleugels blok',
                    'unit' => 'Doos 3 kg',
                    'quantity' => 2,
                    'box_weight_kg' => 3,
                    'price_per_kg' => 7.25,
                    'vat_rate' => 21,
                    'delivered' => 0,
                    'missed_reason' => 'Niet op voorraad',
                ],
            ],
        );

        $this->command?->info('Factuur-demo’s aangemaakt: DEMO-INV-PARTIAL, DEMO-INV-MIXED, DEMO-INV-VRIJ (status concept).');
    }

    protected function customer(
        string $email,
        string $companyName,
        string $contactName,
        bool $isVatExempt = false,
        string $country = 'NL',
    ): Customer {
        return Customer::query()->updateOrCreate(
            ['email' => $email],
            [
                'company_name' => $companyName,
                'contact_name' => $contactName,
                'phone' => '050-1234567',
                'address' => 'Demostraat 1',
                'postal_code' => '9711 AA',
                'city' => $country === 'BE' ? 'Gent' : 'Groningen',
                'country' => $country,
                'vat_number' => $isVatExempt ? 'BE0123456789' : 'NL123456789B01',
                'is_vat_exempt' => $isVatExempt,
            ],
        );
    }
}
