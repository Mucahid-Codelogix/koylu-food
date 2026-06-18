<?php

namespace App\Services\Exact;

class ExactInvoicePushResult
{
    public function __construct(
        public string $invoiceId,
        public ?string $documentNumber,
    ) {}
}
