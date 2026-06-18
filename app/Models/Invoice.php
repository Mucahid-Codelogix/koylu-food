<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\GuardsDeletion;
use App\Services\InvoiceLineCalculator;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use GuardsDeletion, HasFactory;

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
            'exact_synced_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isConcept(): bool
    {
        return $this->status === InvoiceStatus::CONCEPT;
    }

    public function isSent(): bool
    {
        return $this->status === InvoiceStatus::SENT;
    }

    public function displayInvoiceNumber(): string
    {
        return (string) ($this->exact_document_number ?? $this->invoice_number);
    }

    public function isSyncedToExact(): bool
    {
        return filled($this->exact_invoice_id) && filled($this->exact_synced_at);
    }

    /**
     * @return array<int, array{rate: float, taxable_amount: float, vat_amount: float}>
     */
    public function vatByRate(): array
    {
        $this->loadMissing([
            'order.customer',
            'order.items',
            'order.delivery.items',
        ]);

        if (! $this->order) {
            return [];
        }

        return app(InvoiceLineCalculator::class)
            ->totals($this->order, $this->order->delivery)['vat_by_rate'];
    }

    public function formattedVatBreakdown(): string
    {
        return collect($this->vatByRate())
            ->map(function (array $group): string {
                $label = $group['rate'] == 0.0
                    ? 'BTW (0% — vrijgesteld)'
                    : 'BTW ('.number_format($group['rate'], 0, ',', '.').'%)';

                return sprintf(
                    '%s: € %s',
                    $label,
                    number_format($group['vat_amount'], 2, ',', '.'),
                );
            })
            ->implode('<br>');
    }

    public function canBeDeleted(): bool
    {
        return false;
    }

    public function deletionBlockReason(): ?string
    {
        return 'Facturen kunnen niet worden verwijderd.';
    }
}
