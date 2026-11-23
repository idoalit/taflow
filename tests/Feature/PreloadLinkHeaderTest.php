<?php

use Illuminate\Support\Facades\Vite;

/**
 * In production the gateway is an nginx with its default 4k buffer for an
 * upstream's response headers. A response whose headers do not fit is
 * answered with 502, so the Link header that lists the preloaded assets must
 * not grow with the size of the bundle.
 */
it('keeps the response headers within what the gateway can buffer however many assets are preloaded', function (): void {
    $assets = collect(range(1, 40))->mapWithKeys(fn (int $number): array => [
        "https://fipro.poltekkes-smg.ac.id/build/assets/chunk-{$number}-DRC__1Mx.js" => [
            'rel="modulepreload"',
            'crossorigin',
        ],
    ])->all();

    Vite::partialMock()->shouldReceive('preloadedAssets')->andReturn($assets);

    $response = $this->get(route('login'))->assertOk();

    expect(explode(', ', $response->headers->get('Link')))->toHaveCount(10)
        ->and(strlen((string) $response->headers))->toBeLessThan(4096);
});
