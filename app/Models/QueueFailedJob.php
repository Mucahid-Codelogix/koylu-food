<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueFailedJob extends Model
{
    public $timestamps = false;

    protected $table = 'failed_jobs';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    public function jobName(): string
    {
        $payload = json_decode($this->payload, true);

        return (string) ($payload['displayName'] ?? class_basename((string) ($payload['data']['commandName'] ?? 'Onbekende job')));
    }

    public function isExactRelated(): bool
    {
        return str_contains($this->jobName(), 'Exact')
            || str_contains($this->jobName(), 'ImportCustomersFromExact')
            || str_contains($this->jobName(), 'ImportProductsFromExact')
            || str_contains($this->jobName(), 'ImportSuppliersFromExact');
    }

    public function exceptionSummary(): string
    {
        $lines = preg_split('/\R/', (string) $this->exception) ?: [];

        return trim($lines[0] ?? 'Onbekende fout');
    }
}
