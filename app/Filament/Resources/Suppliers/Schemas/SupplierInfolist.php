<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Leverancier informatie')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('name')
                            ->label('Bedrijfsnaam')
                            ->size('lg')
                            ->weight('bold'),

                        IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),

                        TextEntry::make('contact_person')
                            ->label('Contactpersoon')
                            ->placeholder('-')
                            ->icon('heroicon-m-user'),

                        TextEntry::make('email')
                            ->label('E-mail')
                            ->placeholder('-')
                            ->icon('heroicon-m-envelope'),

                        TextEntry::make('phone')
                            ->label('Telefoon')
                            ->placeholder('-')
                            ->icon('heroicon-m-phone'),

                        TextEntry::make('vat_number')
                            ->label('BTW-nummer')
                            ->placeholder('-')
                            ->badge(),

                        TextEntry::make('kvk_number')
                            ->label('KVK-nummer')
                            ->placeholder('-')
                            ->badge(),
                    ]),

                Section::make('Adres')
                    ->columns(1)
                    ->schema([
                        TextEntry::make('address')
                            ->label('Volledig adres')
                            ->placeholder('Geen adres ingevuld')
                            ->prose()
                            ->columnSpanFull(),
                    ]),

                Section::make('Systeem informatie')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Aangemaakt op')
                            ->dateTime('d-m-Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Laatst gewijzigd')
                            ->dateTime('d-m-Y H:i'),
                    ]),
            ]);
    }
}
