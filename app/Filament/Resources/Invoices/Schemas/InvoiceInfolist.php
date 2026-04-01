<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('order.id')
                    ->label('Order'),
                TextEntry::make('invoice_number'),
                TextEntry::make('status'),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('exact_invoice_id')
                    ->placeholder('-'),
                TextEntry::make('pdf_path')
                    ->placeholder('-'),
                TextEntry::make('ubl_path')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
