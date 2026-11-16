<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Support\SingleSignOn;
use App\Support\SystemSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'institution' => $this->institution(app(SystemSettings::class)),
            'sso' => $this->singleSignOn(app(SingleSignOn::class)),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Whose application this is, for the sign-in screen to say so.
     *
     * The logo is read from the documents disk, which in production is an
     * object store on another server. Nobody should be locked out because
     * that server is not answering, so a failure there is reported and the
     * screen falls back to the application's own mark.
     *
     * @return array{name: string, unit: string|null, logo: string|null}
     */
    private function institution(SystemSettings $settings): array
    {
        $institution = $settings->institution();

        return [
            'name' => $institution['name'],
            'unit' => $institution['unit'],
            'logo' => rescue(fn (): ?string => $settings->logo()),
        ];
    }

    /**
     * The single sign-on button, or null when it is not configured.
     *
     * @return array{label: string}|null
     */
    private function singleSignOn(SingleSignOn $singleSignOn): ?array
    {
        return $singleSignOn->enabled() ? ['label' => $singleSignOn->label()] : null;
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
