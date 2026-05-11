<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = [
        'pdf_path' => null,
        'ubl_path' => null,
        'exact_invoice_id' => null,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'invoice_date' => 'datetime',
            'sent_at' => 'datetime',
            'due_date' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isConcept(): bool
    {
        return $this->status === 'concept';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }
}
