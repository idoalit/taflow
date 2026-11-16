<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function (): void {
    config([
        'services.authelia.base_url' => 'https://auth.kampus.test',
        'services.authelia.client_id' => 'fipro',
        'services.authelia.client_secret' => 'rahasia',
        'services.authelia.redirect' => '/auth/sso/callback',
        'services.authelia.label' => 'Masuk dengan SSO Kampus',
    ]);
});

function autheliaIdentity(array $attributes = []): SocialiteUser
{
    return SocialiteUser::fake([
        'id' => 'authelia-sub-1',
        'name' => 'Rani Amelia',
        'email' => 'rani@kampus.test',
        'email_verified' => true,
        ...$attributes,
    ]);
}

test('the login screen offers single sign-on once it is configured', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sso', ['label' => 'Masuk dengan SSO Kampus']));
});

test('the login screen hides single sign-on until it is configured', function () {
    config(['services.authelia.client_id' => null]);

    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sso', null));

    $this->get(route('login.sso'))->assertNotFound();
    $this->get(route('login.sso.callback'))->assertNotFound();
});

test('signing in sends the browser to Authelia with PKCE', function () {
    $response = $this->get(route('login.sso'));

    $location = $response->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://auth.kampus.test/api/oidc/authorization?')
        ->and($query)->toMatchArray([
            'client_id' => 'fipro',
            'redirect_uri' => url('/auth/sso/callback'),
            'response_type' => 'code',
            'code_challenge_method' => 'S256',
        ])
        ->and($query['state'] ?? '')->not->toBe('')
        ->and($query['code_challenge'] ?? '')->not->toBe('')
        ->and(explode(' ', $query['scope']))->toContain('openid', 'email');
});

test('an account the institution already made is signed in by its email', function () {
    $user = User::factory()->create(['email' => 'rani@kampus.test']);
    Socialite::fake('authelia', autheliaIdentity(['email' => 'Rani@Kampus.test']));

    $this->get(route('login.sso.callback', ['code' => 'kode', 'state' => 'state']))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('Authelia is trusted for the second factor', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'rani@kampus.test']);
    Socialite::fake('authelia', autheliaIdentity());

    $this->get(route('login.sso.callback', ['code' => 'kode', 'state' => 'state']))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('nobody gets an account by signing in', function () {
    Socialite::fake('authelia', autheliaIdentity(['email' => 'orang.luar@kampus.test']));

    $this->get(route('login.sso.callback', ['code' => 'kode', 'state' => 'state']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['sso' => 'Akun orang.luar@kampus.test belum terdaftar di Fipro. Hubungi admin program studi.']);

    $this->assertGuest();
    expect(User::query()->where('email', 'orang.luar@kampus.test')->exists())->toBeFalse();
});

test('an email Authelia has not verified is not trusted', function () {
    User::factory()->create(['email' => 'rani@kampus.test']);
    Socialite::fake('authelia', autheliaIdentity(['email_verified' => false]));

    $this->get(route('login.sso.callback', ['code' => 'kode', 'state' => 'state']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

test('a callback that does not match the sign-in it started is refused', function () {
    User::factory()->create(['email' => 'rani@kampus.test']);
    Socialite::fake('authelia', fn () => throw new InvalidStateException);

    $this->get(route('login.sso.callback', ['code' => 'kode', 'state' => 'lain']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['sso' => 'Login SSO gagal. Silakan coba lagi.']);

    $this->assertGuest();
});

test('a sign-in cancelled at Authelia comes back to the login screen', function () {
    $this->get(route('login.sso.callback', ['error' => 'access_denied', 'state' => 'state']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

test('someone already signed in is not sent to Authelia again', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login.sso'))
        ->assertRedirect(route('dashboard', absolute: false));
});
