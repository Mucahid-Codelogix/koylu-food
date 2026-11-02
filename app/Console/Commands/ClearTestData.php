<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\CrateTransaction;
use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Wist alle klanten met alles wat eraan hangt, om schoon te beginnen met de
 * klanten uit de echte Exact-administratie.
 *
 * Artikelen, leveranciers, voertuigen, chauffeurs, beheerders en de
 * Exact-koppeling blijven staan. Er wordt via de query builder gewist, zodat
 * geen observers afgaan en er niets naar Exact gaat.
 */
class ClearTestData extends Command
{
    protected $signature = 'app:clear-test-data
        {--dry-run : Toon alleen wat er gewist zou worden}
        {--force : Niet om bevestiging vragen}';

    protected $description = 'Wist alle klanten met hun bestellingen, routes, leveringen, facturen, kratten, klantprijzen en inlogaccounts';

    public function handle(): int
    {
        $this->table(
            ['Onderdeel', 'Te wissen'],
            collect($this->targets())
                ->map(fn (Builder $records, string $label): array => [$label, $records->count()])
                ->values()
                ->all(),
        );

        $customersWithOwnSettings = Customer::query()
            ->where(fn (Builder $query): Builder => $query->where('min_order_amount', '>', 0)->orWhereNotNull('crate_deposit_price'))
            ->count();

        if ($customersWithOwnSettings > 0) {
            $this->components->warn("{$customersWithOwnSettings} klanten hebben een eigen minimum bestelbedrag of kratprijs. Die komen niet terug met de import uit Exact.");
        }

        if ($this->option('dry-run')) {
            $this->components->info('Proefrun: er is niets gewist.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Alle klanten en alles hierboven definitief wissen?')) {
            $this->components->warn('Afgebroken, er is niets gewist.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            foreach ($this->targets() as $records) {
                $records->delete();
            }
        });

        $this->components->info('Testdata gewist. Draai nu exact:reset-connection en importeer daarna de klanten uit Exact.');

        return self::SUCCESS;
    }

    /**
     * In de volgorde waarin ze gewist moeten worden: de foreign keys staan op
     * restrict, dus eerst wat naar bestellingen en klanten verwijst.
     *
     * @return array<string, Builder<Model>>
     */
    private function targets(): array
    {
        return [
            'Facturen' => Invoice::query(),
            'Routestops' => RouteStop::query(),
            'Routes' => Route::query(),
            'Leverregels' => DeliveryItem::query(),
            'Leveringen' => Delivery::query(),
            'Orderregels' => OrderItem::query(),
            'Bestellingen' => Order::query(),
            'Kratten-mutaties' => CrateTransaction::query(),
            'Klantprijzen' => CustomerProductPrice::query(),
            'Inlogaccounts van klanten' => User::query()->where('role', UserRole::CUSTOMER),
            'Klanten' => Customer::query(),
        ];
    }
}
