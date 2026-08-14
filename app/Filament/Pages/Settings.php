<?php

namespace App\Filament\Pages;

use App\Services\AppSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|null|\BackedEnum $navigationIcon = Heroicon::Cog6Tooth;

    protected static ?string $navigationLabel = 'Instellingen';

    protected static ?string $title = 'Instellingen';

    protected static ?string $slug = 'instellingen';

    protected static string|null|\UnitEnum $navigationGroup = 'Systeem / Beheer';

    protected static ?int $navigationSort = 95;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $settings = app(AppSettings::class);

        $this->form->fill([
            AppSettings::CRATE_DEPOSIT_PRICE => $settings->crateDepositPrice(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kratten')
                    ->description('Statiegeld dat standaard wordt berekend per krat. Per klant kan hiervan worden afgeweken; die klantprijs is dan leidend.')
                    ->icon(Heroicon::ArchiveBox)
                    ->schema([
                        TextInput::make(AppSettings::CRATE_DEPOSIT_PRICE)
                            ->label('Statiegeld per krat')
                            ->helperText('Wordt btw-vrij op de factuur gezet: meegegeven kratten als bijtelling, retour ontvangen kratten als verrekening.')
                            ->prefix('€')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(AppSettings::class);

        $settings->set(
            AppSettings::CRATE_DEPOSIT_PRICE,
            $data[AppSettings::CRATE_DEPOSIT_PRICE],
        );

        Notification::make()
            ->title('Instellingen opgeslagen')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->icon(Heroicon::Check)
                ->submit('save'),
        ];
    }
}
