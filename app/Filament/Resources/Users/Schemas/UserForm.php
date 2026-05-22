<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Gebruikersinformatie')
                    ->columns(2)
                    ->schema([

                        Select::make('customer_id')
                            ->label('Klant')
                            ->relationship('customer', 'company_name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),

                        Select::make('role')
                            ->label('Rol')
                            ->options(UserRole::class)
                            ->default(UserRole::CUSTOMER)
                            ->required(),

                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->email()
                            ->required()
                            ->maxLength(255),

                    ]),

                Section::make('Beveiliging')
                    ->columns(2)
                    ->schema([

                        TextInput::make('password')
                            ->label('Wachtwoord')
                            ->password()
                            ->required()
                            ->revealable(),

                        DateTimePicker::make('email_verified_at')
                            ->label('E-mail geverifieerd op'),

                        DateTimePicker::make('two_factor_confirmed_at')
                            ->label('2FA bevestigd op'),

                    ]),

                Section::make('Tweefactorauthenticatie')
                    ->collapsed()
                    ->schema([

                        Textarea::make('two_factor_secret')
                            ->label('2FA Secret')
                            ->columnSpanFull(),

                        Textarea::make('two_factor_recovery_codes')
                            ->label('Herstelcodes')
                            ->columnSpanFull(),

                    ]),
            ]);
    }
}
