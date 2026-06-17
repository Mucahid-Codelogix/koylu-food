<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Filament\Pages\ExactConnection;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExactOAuthController extends Controller
{
    public function redirect(ExactOnlineClient $client): RedirectResponse
    {
        $this->ensureAdmin();

        return redirect()->away($client->authorizationUrl());
    }

    public function callback(Request $request, ExactOnlineClient $client): RedirectResponse
    {
        $this->ensureAdmin();

        if ($request->filled('error')) {
            return redirect()
                ->to(ExactConnection::getUrl())
                ->with('exact_oauth_error', (string) $request->string('error_description', $request->string('error')));
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $client->handleAuthorizationCallback(
                $request->string('code')->toString(),
                $request->string('state')->toString(),
            );
        } catch (ExactApiException $exception) {
            return redirect()
                ->to(ExactConnection::getUrl())
                ->with('exact_oauth_error', $exception->getMessage());
        }

        return redirect()
            ->to(ExactConnection::getUrl())
            ->with('exact_oauth_success', true);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->role === UserRole::ADMIN, 403);
    }
}
