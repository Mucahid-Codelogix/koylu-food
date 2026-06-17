<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo-accounts (wachtwoord overal: password):
     *
     * | Rol        | E-mail                 | Wat te testen                          |
     * |------------|------------------------|----------------------------------------|
     * | Admin      | admin@koylu.test       | Dashboard, producten, routes, facturen |
     * | Chauffeur  | driver@koylu.test      | Route vandaag: laden starten (DEMO-LOAD) |
     * | Chauffeur  | driver2@koylu.test     | Route vandaag: laden bezig (DEMO-LOADING) |
     * | Chauffeur  | driver3@koylu.test     | Route vandaag: leveren (DEMO-DELIVER)  |
     * | Chauffeur  | driver4@koylu.test     | Route vandaag: deels geleverd + substitutie hele kip |
     * | Klant      | customer1@koylu.test   | Winkel, bestellingen (etc. customer7)  |
     *
     * Factuur-demo’s (concept, Fase A): zoek op DEMO-INV-PARTIAL / MIXED / VRIJ in admin → Facturen.
     * | Klant      | demo-factuur-deel@koylu.test   | Deellevering, 21% btw (78,53 totaal)   |
     * | Klant      | demo-factuur-mixed@koylu.test  | Gemengde btw 9% + 21% (169,50 totaal)  |
     * | Klant      | demo-factuur-vrij@koylu.test   | Btw-vrij buitenlandse klant (64,90)    |
     *
     * Nieuwe bestellingen (status placed, niet op route) staan op het admin-dashboard.
     * Afgeronde route + factuur: gisteren op driver@koylu.test (DEMO-DONE).
     * Toekomstige route: morgen op driver2@koylu.test (DEMO-FUTURE).
     */
    public function run(): void
    {
        $this->call([
            SupplierSeeder::class,
            CustomerSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            CustomerProductPriceSeeder::class,
            VehicleSeeder::class,
            OrderSeeder::class,
            RouteSeeder::class,
            DeliverySeeder::class,
            InvoiceDemoScenarioSeeder::class,
            InvoiceSeeder::class,
        ]);
    }
}
