<?php

use App\Filament\Pages\ExactConnection;
use App\Models\User;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests to login for exact oauth', function () {
    $this->get(route('exact.oauth.redirect'))
        ->assertRedirect();
});

it('forbids non-admin users from exact oauth redirect', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route('exact.oauth.redirect'))
        ->assertForbidden();
});

it('redirects admins to exact authorization url', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('authorizationUrl')
            ->once()
            ->andReturn('https://start.exactonline.nl/api/oauth2/auth?client_id=test');
    });

    $this->actingAs($admin)
        ->get(route('exact.oauth.redirect'))
        ->assertRedirect('https://start.exactonline.nl/api/oauth2/auth?client_id=test');
});

it('validates oauth callback parameters', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('exact.oauth.callback'))
        ->assertInvalid(['code', 'state']);
});

it('redirects oauth errors back to the filament page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('exact.oauth.callback', [
            'error' => 'access_denied',
            'error_description' => 'User denied access',
        ]))
        ->assertRedirect(ExactConnection::getUrl())
        ->assertSessionHas('exact_oauth_error', 'User denied access');
});

it('handles successful oauth callbacks', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('handleAuthorizationCallback')
            ->once()
            ->with('auth-code', 'state-token');
    });

    $this->actingAs($admin)
        ->get(route('exact.oauth.callback', [
            'code' => 'auth-code',
            'state' => 'state-token',
        ]))
        ->assertRedirect(ExactConnection::getUrl())
        ->assertSessionHas('exact_oauth_success', true);
});

it('redirects oauth callback failures back to the filament page', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('handleAuthorizationCallback')
            ->once()
            ->andThrow(new ExactApiException('Token exchange failed'));
    });

    $this->actingAs($admin)
        ->get(route('exact.oauth.callback', [
            'code' => 'auth-code',
            'state' => 'state-token',
        ]))
        ->assertRedirect(ExactConnection::getUrl())
        ->assertSessionHas('exact_oauth_error', 'Token exchange failed');
});

it('allows admins to open the exact connection page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(ExactConnection::getUrl())
        ->assertSuccessful();
});

it('forbids non-admin users from the exact connection page', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(ExactConnection::getUrl())
        ->assertForbidden();
});
