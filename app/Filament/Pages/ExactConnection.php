<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ImportsCustomersFromExact;
use App\Filament\Concerns\ImportsProductsFromExact;
use App\Filament\Concerns\ImportsSuppliersFromExact;
use App\Models\ExactToken;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ExactConnection extends Page
{
    use ImportsCustomersFromExact;
    use ImportsProductsFromExact;
    use ImportsSuppliersFromExact;

    protected string $view = 'filament.admin.pages.exact-connection';

    protected static string|null|\BackedEnum $navigationIcon = Heroicon::Link;

    protected static ?string $navigationLabel = 'Exact-koppeling';

    protected static ?string $title = 'Exact-koppeling';

    protected static ?string $slug = 'exact-koppeling';

    protected static string|null|\UnitEnum $navigationGroup = 'Systeem / Beheer';

    protected static ?int $navigationSort = 90;

    public bool $isConnected = false;

    public ?string $expiresAt = null;

    public ?int $division = null;

    public ?string $configuredDivision = null;

    public ?string $redirectUri = null;

    public function mount(): void
    {
        $this->redirectUri = config('exact.redirect_uri');
        $this->refreshStatus();

        if (session()->pull('exact_oauth_success')) {
            Notification::make()
                ->title('Exact Online gekoppeld')
                ->success()
                ->send();
        }

        if ($error = session()->pull('exact_oauth_error')) {
            Notification::make()
                ->title('Koppeling mislukt')
                ->body($error)
                ->danger()
                ->send();
        }
    }

    public function testConnection(): void
    {
        try {
            $result = app(ExactOnlineClient::class)->testConnection();

            $this->refreshStatus();

            Notification::make()
                ->title('Verbinding OK')
                ->body(sprintf(
                    'Ingelogd als %s (administratie %s)',
                    $result['full_name'] ?? 'onbekend',
                    $result['division'] ?? 'onbekend',
                ))
                ->success()
                ->send();
        } catch (ExactApiException $exception) {
            Notification::make()
                ->title('Verbinding mislukt')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function disconnect(): void
    {
        app(ExactOnlineClient::class)->disconnect();

        $this->refreshStatus();

        Notification::make()
            ->title('Koppeling verbroken')
            ->success()
            ->send();
    }

    private function refreshStatus(): void
    {
        $client = app(ExactOnlineClient::class);
        $token = ExactToken::stored();

        $this->isConnected = $client->isConnected();
        $this->expiresAt = $token?->expires_at?->timezone(config('app.timezone'))->format('d-m-Y H:i');
        $this->division = $token?->division;
        $this->configuredDivision = config('exact.division') ? (string) config('exact.division') : null;
    }
}
