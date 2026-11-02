<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\ExactToken;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Maakt de app klaar om aan een andere Exact-administratie te koppelen.
 *
 * De opgeslagen Exact-ID's van klanten, leveranciers en artikelen horen bij
 * één administratie. Blijven ze staan na het wisselen, dan werkt de sync
 * records bij die in de nieuwe administratie niet bestaan en maakt de import
 * dubbele klanten aan. Artikelcodes blijven staan: daarop koppelt de import
 * de artikelen opnieuw. Facturen blijven ongemoeid.
 */
class ResetExactConnection extends Command
{
    protected $signature = 'exact:reset-connection
        {--dry-run : Toon alleen wat er gewist zou worden}
        {--force : Niet om bevestiging vragen}';

    protected $description = 'Wist de Exact-koppeling en de Exact-ID\'s van klanten, leveranciers en artikelen, om naar een andere administratie over te stappen';

    /**
     * @var array<string, array{model: class-string<Model>, columns: list<string>}>
     */
    private const TARGETS = [
        'Klanten' => [
            'model' => Customer::class,
            'columns' => ['exact_account_id', 'exact_synced_at', 'exact_sync_error'],
        ],
        'Leveranciers' => [
            'model' => Supplier::class,
            'columns' => ['exact_account_id', 'exact_synced_at', 'exact_sync_error'],
        ],
        'Artikelen' => [
            'model' => Product::class,
            'columns' => ['exact_item_id', 'exact_synced_at', 'exact_sync_error'],
        ],
    ];

    public function handle(): int
    {
        $tokenCount = ExactToken::query()->count();

        $this->table(
            ['Onderdeel', 'Te wissen'],
            [
                ...collect(self::TARGETS)
                    ->map(fn (array $target, string $label): array => [$label, $this->linkedRecords($target)->count()])
                    ->values()
                    ->all(),
                ['Exact-koppeling (token)', $tokenCount],
            ],
        );

        if ($this->option('dry-run')) {
            $this->components->info('Proefrun: er is niets gewist.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Exact-koppeling en alle Exact-ID\'s hierboven wissen?')) {
            $this->components->warn('Afgebroken, er is niets gewist.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            foreach (self::TARGETS as $target) {
                $target['model']::withoutTimestamps(
                    fn (): int => $this->linkedRecords($target)->update(array_fill_keys($target['columns'], null)),
                );
            }

            ExactToken::query()->delete();
        });

        $this->components->info('Exact-koppeling gewist. Stel EXACT_DIVISION in en koppel opnieuw via de Exact-koppeling-pagina.');

        return self::SUCCESS;
    }

    /**
     * @param  array{model: class-string<Model>, columns: list<string>}  $target
     * @return Builder<Model>
     */
    private function linkedRecords(array $target): Builder
    {
        return $target['model']::query()->where(function (Builder $query) use ($target): void {
            foreach ($target['columns'] as $column) {
                $query->orWhereNotNull($column);
            }
        });
    }
}
