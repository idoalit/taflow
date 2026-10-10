<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

/**
 * In production the application sits behind a gateway on another machine. What
 * that gateway says about the client is believed; what anybody else says is
 * not, or a visitor could claim any address they liked.
 */
afterEach(function (): void {
    // The trusted list is static on the middleware and would otherwise leak
    // into whichever test runs next.
    TrustProxies::flushState();
});

function trustGateway(string $proxies): void
{
    config(['app.trusted_proxies' => $proxies]);

    app()->getProvider(AppServiceProvider::class)->boot();
}

it('builds https links when the gateway says the visitor came over https', function (): void {
    trustGateway('10.0.0.5, 10.0.1.0/24');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.20'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'ta.kampus.ac.id'])
        ->get('/dashboard')
        ->assertRedirect('https://ta.kampus.ac.id/login');
});

it('does not believe forwarded headers from an address it was not told to trust', function (): void {
    trustGateway('10.0.0.5');

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'jahat.example'])
        ->get('/dashboard')
        // The application's own address, untouched by what the stranger sent.
        ->assertRedirect(route('login'));

    expect(route('login'))->toStartWith('http://')->not->toContain('jahat.example');
});

it('believes nobody when no gateway is configured', function (): void {
    trustGateway('');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    expect(route('login'))->toStartWith('http://');
});
