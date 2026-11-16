<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SingleSignOn;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Sign-in through the institution's Authelia, alongside the password and the
 * passkey.
 *
 * Accounts are still made by a superadmin or an Admin Prodi: Authelia only
 * vouches for who someone is, and the address it vouches for has to belong to
 * an account that already exists here. Nobody gets an account by signing in.
 *
 * The application's own two-factor challenge is not asked after this sign-in.
 * Authelia runs its own second factor, configured on the client's
 * authorization policy, and asking twice would only send people back to the
 * password form.
 */
class SingleSignOnController extends Controller
{
    public function __construct(private SingleSignOn $singleSignOn) {}

    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless($this->singleSignOn->enabled(), 404);

        return Socialite::driver(SingleSignOn::DRIVER)->enablePKCE()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->singleSignOn->enabled(), 404);

        // Authelia sends `error` back when the person cancels or is refused.
        if ($request->filled('error')) {
            return $this->refuse('Login SSO dibatalkan atau ditolak. Silakan coba lagi.');
        }

        try {
            $identity = Socialite::driver(SingleSignOn::DRIVER)->enablePKCE()->user();
        } catch (InvalidStateException|GuzzleException) {
            return $this->refuse('Login SSO gagal. Silakan coba lagi.');
        }

        $email = Str::lower((string) $identity->getEmail());

        if ($email === '' || ($identity->getRaw()['email_verified'] ?? false) !== true) {
            return $this->refuse('Akun SSO Anda belum memiliki email yang terverifikasi. Hubungi pengelola SSO.');
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return $this->refuse("Akun {$email} belum terdaftar di Fipro. Hubungi admin program studi.");
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['sso' => $message]);
    }
}
