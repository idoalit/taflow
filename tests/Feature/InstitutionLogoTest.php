<?php

use App\Support\SystemSettings;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('documents');
});

function uploadInstitutionLogo(string $path = 'institution/logo.png'): string
{
    Storage::disk('documents')->put($path, 'png-bytes');
    app(SystemSettings::class)->put(SystemSettings::INSTITUTION, [
        'name' => 'Universitas Contoh',
        'logo_path' => $path,
    ]);

    return $path;
}

test('the uploaded logo is served to anyone, cached for long', function () {
    uploadInstitutionLogo('institution/logo.jpg');

    $response = $this->get(route('institution.logo'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe('png-bytes')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=31536000');
});

test('there is no logo to serve until one is uploaded', function () {
    $this->get(route('institution.logo'))->assertNotFound();
});

test('the tab icon is the uploaded logo, versioned by the upload', function () {
    $path = uploadInstitutionLogo();
    $logoUrl = route('institution.logo', ['v' => substr(hash('sha256', $path), 0, 12)]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="'.e($logoUrl).'">', false)
        ->assertDontSee('/favicon.svg', false);

    // A new upload is stored under a new name, so the browser asks again.
    uploadInstitutionLogo('institution/logo-baru.png');

    expect(app(SystemSettings::class)->logoUrl())->not->toBe($logoUrl);
});

test('the application keeps its own tab icon when no logo was uploaded', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false)
        ->assertDontSee(route('institution.logo'), false);
});
