<?php

use App\Models\ExactSyncLog;
use App\Models\ExactToken;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('doet niets wanneer Exact niet gekoppeld is', function () {
    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturnFalse();
        $mock->shouldNotReceive('testConnection');
    });

    $this->artisan('exact:refresh-connection')
        ->expectsOutputToContain('niet gekoppeld')
        ->assertSuccessful();
});

it('vernieuwt de koppeling zodat de refresh-token geldig blijft', function () {
    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturnTrue();
        $mock->shouldReceive('testConnection')->once()->andReturn([
            'division' => 4480154,
            'full_name' => 'Koylu',
            'email' => 'info@koylufood.com',
        ]);
    });

    $this->artisan('exact:refresh-connection')
        ->expectsOutputToContain('4480154')
        ->assertSuccessful();
});

it('meldt een mislukte vernieuwing in het sync-logboek', function () {
    ExactToken::storeOrUpdate([
        'access_token' => 'a',
        'refresh_token' => 'r',
        'expires_at' => now()->addMinutes(10),
        'division' => 1,
    ]);

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturnTrue();
        $mock->shouldReceive('testConnection')->once()
            ->andThrow(new ExactApiException('Token ongeldig'));
    });

    $this->artisan('exact:refresh-connection')->assertFailed();

    expect(ExactSyncLog::query()->where('action', 'refresh_connection')->exists())->toBeTrue();
});
