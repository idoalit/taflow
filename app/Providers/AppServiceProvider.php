<?php

namespace App\Providers;

use App\Enums\ExamStage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

        return $exception instanceof AccessDeniedHttpException
            ? $this->meaningfulMessage($exception->getMessage())
            : null;
    }

    protected function meaningfulMessage(string $message): ?string
    {
        return $message === '' || $message === 'This action is unauthorized.' ? null : $message;
    }
}
