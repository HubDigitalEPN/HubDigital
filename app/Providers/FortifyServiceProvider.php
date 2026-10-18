<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Models\User;
use App\Services\Security\DummyPasswordHash;
use App\Services\Security\TurnstileVerifier;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\Laravel\Fortify\Contracts\LoginResponse::class, \App\Http\Responses\LoginPorPerfilResponse::class);
        $this->app->singleton(\Laravel\Fortify\Contracts\TwoFactorLoginResponse::class, \App\Http\Responses\LoginPorPerfilResponse::class);
        // Una solicitud de recuperación siempre responde igual, exista o no la
        // cuenta. El enlace real solo se envía cuando el broker encuentra al usuario.
        $this->app->singleton(GenericPasswordResetLinkResponse::class);
        $this->app->singleton(
            FailedPasswordResetLinkRequestResponse::class,
            fn ($app) => $app->make(GenericPasswordResetLinkResponse::class),
        );
        $this->app->singleton(
            SuccessfulPasswordResetLinkRequestResponse::class,
            fn ($app) => $app->make(GenericPasswordResetLinkResponse::class),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureEmailVerification();
        $this->configureRateLimiting();
        $this->configureAuthentication();

        $minutes = (int) config('auth.remember_lifetime', 60 * 24 * 30);
        if (filled(config('app.key')) && $minutes > 0 && method_exists(Auth::guard('web'), 'setRememberDuration')) {
            Auth::guard('web')->setRememberDuration($minutes);
        }
    }

    /**
     * Personaliza el correo de verificación con asunto en español y una
     * plantilla alineada a la identidad visual de Hub Digital (en lugar de
     * la plantilla genérica en inglés de Laravel).
     */
    private function configureEmailVerification(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $data = [
                'user' => $notifiable,
                'url' => $url,
                'expireMinutes' => (int) config('auth.verification.expire', 60),
            ];

            return (new MailMessage)
                ->subject('Verifica tu correo para activar tu cuenta')
                ->view('emails.auth.verify-email', $data)
                ->text('emails.auth.verify-email-text', $data);
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
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
            $throttleKey = Str::transliterate(User::normalizarEmail((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Autenticación canónica por correo. Fortify conserva el resto de su
     * pipeline (throttle, regeneración de sesión y reto 2FA).
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            app(TurnstileVerifier::class)->validateLogin($request);

            $email = User::normalizarEmail((string) $request->input(Fortify::username()));
            $request->merge([Fortify::username() => $email]);

            $user = User::query()->where('email_normalizado', $email)->first();
            $dummyHash = app(DummyPasswordHash::class)->value();

            // Verificar también un hash válido cuando el usuario no existe
            // reduce diferencias de tiempo que facilitarían enumerar cuentas.
            $hash = $user?->password ?? $dummyHash;

            if (! Hash::check((string) $request->input('password'), $hash) || ! $user) {
                return null;
            }

            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => Hash::make((string) $request->input('password'))])->save();
            }

            return $user;
        });
    }
}
