<?php

namespace App\Providers;

use App\Enums\ExamStage;
use App\Policies\RolePolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use SocialiteProviders\Authelia\Provider as AutheliaProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();
        $this->configureErrorPages();
        $this->configureTrustedProxies();
        $this->configureUrlScheme();

        // spatie's Role is not ours to annotate with #[UsePolicy].
        Gate::policy(Role::class, RolePolicy::class);

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('authelia', AutheliaProvider::class);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // The whole interface is in Bahasa Indonesia, so dates are formatted in
        // that locale everywhere rather than per call site.
        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');

        // Resources are consumed as Inertia props, never as a REST payload, so
        // the "data" envelope would only get in the page components' way.
        JsonResource::withoutWrapping();

        // Exam verdicts are stored against either exam table, so the type column
        // holds a short alias instead of a class name that a rename would break.
        Relation::morphMap([
            ExamStage::Proposal->morphAlias() => ExamStage::Proposal->modelClass(),
            ExamStage::Research->morphAlias() => ExamStage::Research->modelClass(),
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Believe the gateway in front of the application, and nobody else, about
     * who the client is and whether they came over https. Read from
     * configuration here rather than set in bootstrap/app.php, where the
     * configuration is not loaded yet.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = config('app.trusted_proxies');

        if (! is_string($proxies) || trim($proxies) === '') {
            return;
        }

        TrustProxies::at(trim($proxies) === '*'
            ? '*'
            : array_values(array_filter(array_map(trim(...), explode(',', $proxies)))));
    }

    /**
     * Build every link over https when the application's own address is
     * https. Not every gateway says so in X-Forwarded-Proto, and a page served
     * over https cannot load its scripts or follow its redirects over http.
     */
    protected function configureUrlScheme(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Render refusals and missing pages as a themed Inertia page rather than
     * Laravel's bare "This action is unauthorized." Server faults keep their
     * stack trace locally, where that is what you actually want to see.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $status = $response->statusCode();

            if (! in_array($status, [403, 404, 419, 500, 503], true)) {
                return null;
            }

            if (in_array($status, [500, 503], true) && app()->environment('local', 'testing')) {
                return null;
            }

            return $response->render('error', [
                'status' => $status,
                'reason' => $this->denialReason($response->exception),
            ])->withSharedData();
        });
    }

    /**
     * A policy that denied with its own message has already explained itself in
     * the user's language. Laravel wraps that refusal in an HTTP exception
     * before the response callback runs, so the chain is walked to find the
     * original; its generic fallback is dropped in favour of the page's copy.
     */
    protected function denialReason(?Throwable $exception): ?string
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof AuthorizationException) {
                return $this->meaningfulMessage($current->getMessage());
            }
        }

        // `abort(403, '…')` raises a plain HTTP exception rather than an access
        // denied one, and the workspace gates refuse that way.
        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403
            ? $this->meaningfulMessage($exception->getMessage())
            : null;
    }

    protected function meaningfulMessage(string $message): ?string
    {
        return $message === '' || $message === 'This action is unauthorized.' ? null : $message;
    }
}
