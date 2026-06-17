<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['access_token', 'refresh_token', 'expires_at', 'division'])]
class ExactToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'division' => 'integer',
        ];
    }

    public static function stored(): ?self
    {
        return static::query()->first();
    }

    /**
     * @param  array{access_token: string, refresh_token: string, expires_at: CarbonInterface, division?: int|null}  $attributes
     */
    public static function storeOrUpdate(array $attributes): self
    {
        $token = static::stored() ?? new self;

        $token->fill($attributes);
        $token->save();

        return $token;
    }
}
