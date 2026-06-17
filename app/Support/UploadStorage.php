<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

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

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return self::disk()->url($path);
    }
}
