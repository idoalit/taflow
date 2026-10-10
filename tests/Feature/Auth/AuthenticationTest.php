<?php

use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('the front page is the login screen', function () {
    config(['institution.name' => 'Universitas Contoh', 'institution.unit' => 'Fakultas Teknik']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/login')
            ->where('institution', [
                'name' => 'Universitas Contoh',
                'unit' => 'Fakultas Teknik',
                'logo' => null,
            ]));
});

test('the application is called what it is configured to be called', function () {
    config(['app.name' => 'Nama Lain']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="application-name" content="Nama Lain">', false)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('name', 'Nama Lain'));
});

test('the front page sends a signed-in user to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});

test('the login screen shows the logo the institution uploaded', function () {
    Storage::disk('documents')->put('institution/logo.png', 'png-bytes');
    app(SystemSettings::class)->put(SystemSettings::INSTITUTION, [
        'name' => 'Universitas Contoh',
        'logo_path' => 'institution/logo.png',
    ]);

    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('institution.name', 'Universitas Contoh')
            ->where('institution.logo', 'data:image/png;base64,'.base64_encode('png-bytes')));
});

test('the login screen still opens when the logo cannot be read', function () {
    Exceptions::fake();

    app(SystemSettings::class)->put(SystemSettings::INSTITUTION, [
        'name' => 'Universitas Contoh',
        'logo_path' => 'institution/logo.png',
    ]);

    // The object store the documents disk points at is not answering.
    Storage::shouldReceive('disk')->with('documents')->andThrow(new RuntimeException('Connection refused'));

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('institution.name', 'Universitas Contoh')
            ->where('institution.logo', null));

    Exceptions::assertReported(RuntimeException::class);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
