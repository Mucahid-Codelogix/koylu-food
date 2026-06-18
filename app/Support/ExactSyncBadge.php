<?php

namespace App\Support;

class ExactSyncBadge
{
    public static function label(?string $error, ?\DateTimeInterface $syncedAt, bool $synced = false): string
    {
        if (filled($error)) {
            return 'Fout';
        }

        if ($synced || filled($syncedAt)) {
            return 'Gesynced';
        }

        return 'Niet gesynced';
    }

    public static function invoiceLabel(?string $error, bool $isSyncedToExact): string
    {
        if (filled($error)) {
            return 'Fout';
        }

        if ($isSyncedToExact) {
            return 'Geboekt';
        }

        return 'Concept';
    }

    public static function color(?string $error, ?\DateTimeInterface $syncedAt, bool $synced = false): string
    {
        if (filled($error)) {
            return 'danger';
        }

        if ($synced || filled($syncedAt)) {
            return 'success';
        }

        return 'gray';
    }

    public static function invoiceColor(?string $error, bool $isSyncedToExact): string
    {
        if (filled($error)) {
            return 'danger';
        }

        if ($isSyncedToExact) {
            return 'success';
        }

        return 'gray';
    }
}
