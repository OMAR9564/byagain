<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\UniformPasswordResetLinkResponse;
use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Fortify runs headless: it owns the authentication logic, this app owns every
 * view (FR-088, all copy from lang/en/).
 */
final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fortify's default failure response names the problem — "we can't
        // find a user with that email address" — which turns the reset form
        // into an account-existence oracle. Replaced with a response identical
        // to the success case (FR-004).
        $this->app->singleton(
            FailedPasswordResetLinkRequestResponse::class,
            UniformPasswordResetLinkResponse::class,
        );
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->registerViews();
        $this->registerRateLimiters();
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn (): View => view('auth.login'));

        Fortify::registerView(function (): View {
            // An administrator can close registration for the instance
            // (FR-075). Refusing here rather than hiding the link means a
            // bookmarked URL is refused too.
            if (Setting::read(Setting::REGISTRATION_OPEN, true) === false) {
                throw new HttpException(403, __('errors.forbidden.body'));
            }

            return view('auth.register');
        });

        Fortify::requestPasswordResetLinkView(fn (): View => view('auth.forgot-password'));

        Fortify::resetPasswordView(fn (Request $request): View => view('auth.reset-password', [
            'request' => $request,
        ]));

        Fortify::verifyEmailView(fn (): View => view('auth.verify-email'));

        Fortify::confirmPasswordView(fn (): View => view('auth.confirm-password'));
    }

    /**
     * Throttling for the two endpoints worth attacking (FR-006).
     *
     * Login is keyed on address *and* IP so one person guessing does not lock
     * the real owner out, and so a botnet spreading attempts across addresses
     * still hits a wall.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        // Password reset is rate limited by address alone: the point is to
        // stop the endpoint being used as a mail cannon at somebody else's
        // inbox.
        RateLimiter::for('reset-password', function (Request $request): Limit {
            return Limit::perMinutes(15, 3)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('verification-notification', function (Request $request): Limit {
            return Limit::perMinutes(15, 3)->by((string) $request->user()?->getKey());
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request): Limit {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                (is_string($credentialId) && $credentialId !== '' ? $credentialId : $request->session()->getId())
                .'|'.$request->ip()
            );
        });
    }
}
