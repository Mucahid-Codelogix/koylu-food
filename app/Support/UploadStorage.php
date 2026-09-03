<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadStorage
{
    public static function diskName(): string
    {
        return (string) config('filesystems.upload_disk', 'public');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function usesSpaces(): bool
    {
        return self::diskName() === 'spaces';
    }

    public static function directory(string $folder): string
    {
        if (self::usesSpaces()) {
            return config('filesystems.upload_env').'/'.$folder;
        }

        return $folder;
    }

    /**
     * Bestaat het bestand op de uploaddisk?
     *
     * Storage::exists() gooit bij een S3-disk alsnog een exception als de bucket
     * niet bereikbaar is: de 'throw' => false vlag geldt alleen voor lees- en
     * schrijfacties. Een onbereikbare opslag mag geen pagina laten crashen, dus
     * behandelen we dat als "niet gevonden" en loggen we het.
     */
    public static function exists(?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        try {
            return self::disk()->exists($path);
        } catch (Throwable $e) {
            self::logFailure('bestaan controleren', $path, $e);

            return false;
        }
    }

    /**
     * Inhoud van het bestand, of null als het ontbreekt of de opslag hapert.
     */
    public static function read(?string $path): ?string
    {
        if (! self::exists($path)) {
            return null;
        }

        try {
            return self::disk()->get($path);
        } catch (Throwable $e) {
            self::logFailure('uitlezen', $path, $e);

            return null;
        }
    }

    private static function logFailure(string $action, string $path, Throwable $e): void
    {
        Log::warning("Upload-opslag: {$action} mislukt.", [
            'disk' => self::diskName(),
            'path' => $path,
            'exception' => $e->getMessage(),
        ]);
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return self::disk()->url($path);
    }
}
