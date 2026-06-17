<?php

use App\Models\ExactToken;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reports disconnected when no token is stored', function () {
    $client = app(ExactOnlineClient::class);

    expect($client->isConnected())->toBeFalse();
});

it('reports connected when a refresh token is stored', function () {
    ExactToken::storeOrUpdate([
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addMinutes(10),
        'division' => 123456,
    ]);

    expect(app(ExactOnlineClient::class)->isConnected())->toBeTrue();
});

it('disconnect removes stored tokens', function () {
    ExactToken::storeOrUpdate([
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addMinutes(10),
    ]);

    app(ExactOnlineClient::class)->disconnect();

    expect(ExactToken::query()->count())->toBe(0)
        ->and(app(ExactOnlineClient::class)->isConnected())->toBeFalse();
});

it('rejects oauth callbacks with an invalid state', function () {
    session(['exact_oauth_state' => 'expected-state']);

    expect(fn () => app(ExactOnlineClient::class)->handleAuthorizationCallback('code', 'wrong-state'))
        ->toThrow(ExactApiException::class, 'Ongeldige OAuth state.');
});

it('throws when requesting a connection without tokens', function () {
    expect(fn () => app(ExactOnlineClient::class)->connection())
        ->toThrow(ExactApiException::class, 'Exact Online is niet gekoppeld.');
});

it('encrypts tokens at rest', function () {
    $token = ExactToken::storeOrUpdate([
        'access_token' => 'secret-access',
        'refresh_token' => 'secret-refresh',
        'expires_at' => now()->addMinutes(10),
    ]);

    $raw = DB::table('exact_tokens')->where('id', $token->id)->first();

    expect($raw->access_token)->not->toBe('secret-access')
        ->and($raw->refresh_token)->not->toBe('secret-refresh')
        ->and($token->fresh()->access_token)->toBe('secret-access');
});

it('can persist a division on an existing token', function () {
    ExactToken::storeOrUpdate([
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addMinutes(10),
    ]);

    ExactToken::storeOrUpdate([
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addMinutes(10),
        'division' => 424242,
    ]);

    expect(ExactToken::stored()?->division)->toBe(424242);
});
