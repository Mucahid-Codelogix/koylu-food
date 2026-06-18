<?php

namespace App\Models\Concerns;

use App\Exceptions\RecordNotDeletableException;

trait GuardsDeletion
{
    abstract public function canBeDeleted(): bool;

    abstract public function deletionBlockReason(): ?string;

    protected static function bootGuardsDeletion(): void
    {
        static::deleting(function (self $model): void {
            if (! $model->canBeDeleted()) {
                throw new RecordNotDeletableException(
                    $model->deletionBlockReason() ?? 'Dit record kan niet worden verwijderd.',
                );
            }
        });
    }
}
