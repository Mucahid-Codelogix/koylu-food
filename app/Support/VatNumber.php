<?php

namespace App\Support;

class VatNumber
{
    /**
     * Return a normalized VAT number for Exact, or null when invalid / empty.
     */
    public static function forExact(?string $vatNumber, string $countryCode = 'NL'): ?string
    {
        if (! filled($vatNumber)) {
            return null;
        }

        $normalized = self::normalize((string) $vatNumber);

        return match (strtoupper($countryCode)) {
            'BE' => self::isValidBelgian($normalized) ? $normalized : null,
            default => self::isValidDutch($normalized) ? $normalized : null,
        };
    }

    public static function normalize(string $vatNumber): string
    {
        return strtoupper(preg_replace('/[\s.]/', '', $vatNumber) ?? $vatNumber);
    }

    public static function isValidDutch(string $vatNumber): bool
    {
        $normalized = self::normalize($vatNumber);

        if (! preg_match('/^NL(\d{9})B\d{2}$/', $normalized, $matches)) {
            return false;
        }

        $digits = $matches[1];
        $sum = 0;

        for ($index = 0; $index < 8; $index++) {
            $sum += (int) $digits[$index] * (9 - $index);
        }

        $check = $sum % 11;

        if ($check >= 10) {
            return false;
        }

        return $check === (int) $digits[8];
    }

    public static function isValidBelgian(string $vatNumber): bool
    {
        $normalized = self::normalize($vatNumber);

        if (! preg_match('/^BE(\d{10})$/', $normalized, $matches)) {
            return false;
        }

        $digits = $matches[1];
        $base = (int) substr($digits, 0, 8);
        $check = (int) substr($digits, 8, 2);
        $mod = $base % 97;

        return $check === ($mod === 0 ? 97 : $mod);
    }
}
