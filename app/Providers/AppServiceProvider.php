<?php

namespace App\Providers;

use App\Services\SystemMailConfigurationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Support\PortalCopy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTranslations();
        $this->configureSystemMail();
        $this->preventExternalNotificationsDuringValidation();
    }

    /** La base prevalece sobre .env tras la primera configuración administrativa. */
    protected function configureSystemMail(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        try {
            $service = app(SystemMailConfigurationService::class);
            // La configuración existente necesita una sola lectura. Repetir la
            // importación y la aplicación duplicaba consultas en cada arranque HTTP.
            $configuration = $service->stored();
            if ($configuration === null) {
                $service->importEnvironmentIfMissing();
                $configuration = $service->stored();
            }
            if ($configuration !== null) {
                $service->apply($configuration);
            }
        } catch (\Throwable $exception) {
            // Migraciones, instalación inicial o una caída de PostgreSQL deben
            // conservar el fallback de entorno sin revelar secretos en logs.
            Log::warning('No se pudo cargar la configuración SMTP administrativa.', [
                'exception_type' => $exception::class,
            ]);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureTranslations(): void
    {
        $this->app['translator']->addLines([
            'validation.uploaded' => 'El archivo :attribute no pudo cargarse. Verifica que sea un PDF de máximo 10 MB.',
            'validation.attributes.pdfFirmado' => 'acta firmada',
            'validation.attributes.documentoIdentidad' => 'documento de identidad',
            'validation.attributes.documentoIdentidadSolo' => 'documento de identidad',
        ], 'en');
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
     * La restauracion se valida sobre datos coordinados que pueden conservar
     * destinatarios reales. MAIL_MAILER=log por si solo no cubre Web Push u
     * otros canales de Laravel Notification; en modo de validacion se permite
     * solamente la campana interna de base de datos.
     */
    protected function preventExternalNotificationsDuringValidation(): void
    {
        if (! config('hubdigital.validation_mode')) {
            return;
        }

        Event::listen(NotificationSending::class, static function (NotificationSending $event): ?bool {
            if ($event->channel === 'database') {
                return null;
            }

            $esVerificacionAutorizada = config('hubdigital.allow_auth_emails_during_validation')
                && $event->channel === 'mail'
                && $event->notification instanceof VerifyEmail;

            return $esVerificacionAutorizada ? null : false;
        });
    }
}
