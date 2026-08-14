<?php

use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactXmlClient;
use Illuminate\Support\Facades\Http;
use Picqer\Financials\Exact\Connection;

beforeEach(function () {
    config()->set('exact.base_url', 'https://start.exactonline.nl');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getAccessToken')->andReturn('test-token');
    $connection->shouldReceive('getDivision')->andReturn(123456);

    $client = Mockery::mock(ExactOnlineClient::class);
    $client->shouldReceive('call')->andReturnUsing(fn (callable $callback) => $callback($connection));

    $this->app->instance(ExactOnlineClient::class, $client);
});

it('posts the xml as a raw body with the expect header disabled', function () {
    Http::fake([
        '*' => Http::response('<eExact><Messages /></eExact>'),
    ]);

    $messages = app(ExactXmlClient::class)->upload('MatchSets', '<eExact />');

    expect($messages)->toBe([]);

    Http::assertSent(function ($request) {
        expect($request->url())->toContain('XMLUpload.aspx')
            ->and($request->url())->toContain('Topic=MatchSets')
            ->and($request->url())->toContain('_Division_=123456')
            ->and($request->body())->toBe('<eExact />')
            ->and($request->header('Content-Type'))->toContain('text/xml')
            ->and($request->header('Authorization'))->toContain('Bearer test-token')
            ->and($request->header('Expect'))->toBe(['']);

        return true;
    });
});

it('reads the type and description of each message', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
            <eExact>
              <Messages>
                <Message type="0">
                  <Topic code="MatchSets" node="MatchSet" />
                  <Description>entry=99999998 Niet gevonden!</Description>
                </Message>
                <Message type="2">
                  <Description>Rekening: 1300 - 2 Regels Afgeletterd.</Description>
                </Message>
              </Messages>
            </eExact>
            XML),
    ]);

    $messages = app(ExactXmlClient::class)->upload('MatchSets', '<eExact />');

    expect($messages)->toHaveCount(2);

    /** Type 0 is een fout, type 2 is de succesmelding van Exact zelf. */
    expect($messages[0]->description)->toBe('entry=99999998 Niet gevonden!')
        ->and($messages[0]->isError())->toBeTrue()
        ->and($messages[1]->description)->toBe('Rekening: 1300 - 2 Regels Afgeletterd.')
        ->and($messages[1]->isError())->toBeFalse();
});

it('fails on an unreadable response', function () {
    Http::fake([
        '*' => Http::response('geen xml'),
    ]);

    expect(fn () => app(ExactXmlClient::class)->upload('MatchSets', '<eExact />'))
        ->toThrow(ExactApiException::class, 'Onleesbaar antwoord');
});

it('fails on an http error', function () {
    Http::fake([
        '*' => Http::response('nope', 401),
    ]);

    expect(fn () => app(ExactXmlClient::class)->upload('MatchSets', '<eExact />'))
        ->toThrow(ExactApiException::class, 'HTTP 401');
});
