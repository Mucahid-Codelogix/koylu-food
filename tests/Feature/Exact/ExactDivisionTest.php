<?php

use App\Filament\Pages\ExactConnection;
use App\Models\ExactToken;
use App\Models\User;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Picqer\Financials\Exact\Connection;
use Psr\Http\Message\RequestInterface;

const TEST_DIVISION = 4480154;
const PRODUCTION_DIVISION = 459429;

/**
 * Laat de picqer-connection tegen nep-antwoorden praten. Per stukje URL een
 * antwoord: een array wordt de JSON-body, een getal een HTTP-foutcode.
 *
 * @param  array<string, array<string, mixed>|int>  $responses
 * @return ArrayObject<int, string>
 */
function fakeExactHttp(array $responses): ArrayObject
{
    $requestedUrls = new ArrayObject;

    app()->bind(Connection::class, function () use ($responses, $requestedUrls): Connection {
        $connection = new Connection;

        $connection->insertMiddleWare(fn (callable $handler): callable => function (RequestInterface $request) use ($responses, $requestedUrls): PromiseInterface {
            $requestedUrls->append((string) $request->getUri());

            foreach ($responses as $urlPart => $response) {
                if (! str_contains((string) $request->getUri(), $urlPart)) {
                    continue;
                }

                if (is_int($response)) {
                    return Create::rejectionFor(RequestException::create($request, new Response($response)));
                }

                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], json_encode($response)));
            }

            throw new RuntimeException('Onverwachte Exact-aanroep: '.$request->getUri());
        });

        return $connection;
    });

    return $requestedUrls;
}

/**
 * @param  array<string, mixed>  $row
 * @return array{d: array{results: list<array<string, mixed>>}}
 */
function exactResult(array $row): array
{
    return ['d' => ['results' => [$row]]];
}

function storeExactToken(array $attributes = []): ExactToken
{
    return ExactToken::storeOrUpdate(array_merge([
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addMinutes(10),
        'division' => TEST_DIVISION,
    ], $attributes));
}

beforeEach(function () {
    config()->set('exact.division', PRODUCTION_DIVISION);
});

it('gebruikt de administratie uit de config, ook als het token nog de test-administratie heeft', function () {
    storeExactToken(['division' => TEST_DIVISION]);

    expect((int) app(ExactOnlineClient::class)->connection()->getDivision())->toBe(PRODUCTION_DIVISION);
});

it('weigert een verbinding als er geen administratie is ingesteld, ook als het token er een heeft', function () {
    config()->set('exact.division', null);
    storeExactToken(['division' => TEST_DIVISION]);

    expect(fn () => app(ExactOnlineClient::class)->connection())
        ->toThrow(ExactApiException::class, 'EXACT_DIVISION is niet ingesteld');
});

it('laat verbinding testen de ingestelde administratie vasthouden en slaat de naam op', function () {
    storeExactToken(['division' => TEST_DIVISION]);

    $requestedUrls = fakeExactHttp([
        'current/Me' => exactResult(['CurrentDivision' => TEST_DIVISION, 'FullName' => 'Koylu Admin', 'Email' => 'admin@koylu.test']),
        'system/Divisions' => exactResult(['Code' => PRODUCTION_DIVISION, 'Description' => 'Koylu Food B.V.']),
    ]);

    $result = app(ExactOnlineClient::class)->testConnection();

    expect($result)->toMatchArray([
        'division' => PRODUCTION_DIVISION,
        'division_name' => 'Koylu Food B.V.',
        'full_name' => 'Koylu Admin',
    ])
        ->and(ExactToken::stored())
        ->division->toBe(PRODUCTION_DIVISION)
        ->division_name->toBe('Koylu Food B.V.')
        ->and(collect($requestedUrls)->contains(fn (string $url): bool => str_contains($url, '/'.PRODUCTION_DIVISION.'/system/Divisions')))->toBeTrue();
});

it('laat verbinding testen zonder ingestelde administratie mislukken zonder Exact aan te roepen', function () {
    config()->set('exact.division', null);
    storeExactToken(['division' => TEST_DIVISION]);

    $requestedUrls = fakeExactHttp([
        'current/Me' => exactResult(['CurrentDivision' => PRODUCTION_DIVISION, 'FullName' => 'Koylu Admin', 'Email' => 'admin@koylu.test']),
    ]);

    expect(fn () => app(ExactOnlineClient::class)->testConnection())
        ->toThrow(ExactApiException::class, 'EXACT_DIVISION is niet ingesteld');

    expect($requestedUrls)->toHaveCount(0)
        ->and(ExactToken::stored()->division)->toBe(TEST_DIVISION);
});

it('start het koppelen niet zonder ingestelde administratie', function () {
    config()->set('exact.division', null);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('exact.oauth.redirect'))
        ->assertRedirect(ExactConnection::getUrl())
        ->assertSessionHas('exact_oauth_error', fn (string $message): bool => str_contains($message, 'EXACT_DIVISION is niet ingesteld'));
});

it('laat de naam leeg als Exact geen toegang geeft tot de administratie', function () {
    storeExactToken(['division' => PRODUCTION_DIVISION, 'division_name' => 'Oude naam']);

    fakeExactHttp([
        'current/Me' => exactResult(['CurrentDivision' => TEST_DIVISION, 'FullName' => 'Koylu Admin', 'Email' => 'admin@koylu.test']),
        'system/Divisions' => 401,
    ]);

    $result = app(ExactOnlineClient::class)->testConnection();

    expect($result)->toMatchArray(['division' => PRODUCTION_DIVISION, 'division_name' => null])
        ->and(ExactToken::stored()->division_name)->toBeNull();
});

it('slaat bij het koppelen de ingestelde administratie op in plaats van de laatst geopende', function () {
    session(['exact_oauth_state' => 'state-token']);

    fakeExactHttp([
        'oauth2/token' => ['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => '600'],
        'current/Me' => exactResult(['CurrentDivision' => TEST_DIVISION]),
        'system/Divisions' => exactResult(['Code' => PRODUCTION_DIVISION, 'Description' => 'Koylu Food B.V.']),
    ]);

    app(ExactOnlineClient::class)->handleAuthorizationCallback('auth-code', 'state-token');

    expect(ExactToken::stored())
        ->refresh_token->toBe('new-refresh')
        ->division->toBe(PRODUCTION_DIVISION)
        ->division_name->toBe('Koylu Food B.V.');
});

it('toont de naam van de administratie op de koppelingspagina', function () {
    storeExactToken(['division' => PRODUCTION_DIVISION, 'division_name' => 'Koylu Food B.V.']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(ExactConnection::getUrl())
        ->assertSuccessful()
        ->assertSee('Administratie in gebruik')
        ->assertSee('Koylu Food B.V.')
        ->assertDontSee('Wijkt af van de config');
});

it('waarschuwt op de koppelingspagina als het token nog een andere administratie heeft', function () {
    storeExactToken(['division' => TEST_DIVISION]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(ExactConnection::getUrl())
        ->assertSuccessful()
        ->assertSee('Wijkt af van de config');
});

it('waarschuwt op de koppelingspagina als er geen administratie is ingesteld', function () {
    config()->set('exact.division', null);

    $this->actingAs(User::factory()->admin()->create())
        ->get(ExactConnection::getUrl())
        ->assertSuccessful()
        ->assertSee('is niet ingesteld');
});
