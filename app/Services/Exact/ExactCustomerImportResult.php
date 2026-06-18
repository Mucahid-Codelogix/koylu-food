<?php

namespace App\Services\Exact;

class ExactCustomerImportResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
    ) {}

    public function totalProcessed(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }

    public function summary(): string
    {
        return sprintf(
            '%d aangemaakt, %d bijgewerkt, %d overgeslagen.',
            $this->created,
            $this->updated,
            $this->skipped,
        );
    }
}
