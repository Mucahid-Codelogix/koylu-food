<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Gebruikersinformatie')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('customer.company_name')
                            ->label('Klant')
                            ->placeholder('-'),

                        TextEntry::make('name')
                            ->label('Naam'),

                        TextEntry::make('role')
                            ->label('Rol')
                            ->badge(),

                        TextEntry::make('email')
                            ->label('E-mailadres'),

                    ]),

                Section::make('Beveiliging')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('email_verified_at')
                            ->label('E-mail geverifieerd op')
                            ->dateTime()
                            ->placeholder('-'),

                        TextEntry::make('two_factor_confirmed_at')
                            ->label('2FA bevestigd op')
                            ->dateTime()
                            ->placeholder('-'),

                    ]),

                Section::make('Tweefactorauthenticatie')
                    ->collapsed()
                    ->schema([

                        TextEntry::make('two_factor_secret')
                            ->label('2FA Secret')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('two_factor_recovery_codes')
                            ->label('Herstelcodes')
                            ->placeholder('-')
                            ->columnSpanFull(),

                    ]),

                Section::make('Systeem')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('created_at')
                            ->label('Aangemaakt op')
                            ->dateTime()
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Laatst bijgewerkt')
                            ->dateTime()
                            ->placeholder('-'),

                    ]),
            ]);
    }
}
