<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('a user cannot delete their own account', function () {
    $user = User::factory()->create();

    // The endpoint is gone entirely — accounts are removed by a superadmin or a
    // program admin, never by the account holder.
    expect(Route::has('profile.destroy'))->toBeFalse();

    // The GET/PATCH routes still exist at this path, so DELETE is rejected as
    // an unsupported method rather than a missing page.
    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});

test('the profile page no longer offers account deletion', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Hapus Akun');
});
