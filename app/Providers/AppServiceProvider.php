<?php

namespace App\Providers;

use App\Services\SystemMailConfigurationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
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
        $this->configureUploadRules();
        $this->configureSystemMail();
        $this->preventExternalNotificationsDuringValidation();
    }

    /** La base prevalece sobre .env tras la primera configuración administrativa. */
    protected function configureSystemMail(): void
    {
        if (app()->environment('testing')
            || (app()->environment('local') && config('mail.default') === 'log')) {
            // El lanzador localhost pide log explícitamente. No sustituirlo
            // por el SMTP de una base restaurada con destinatarios reales.
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
            'validation.uploaded' => 'El archivo :attribute no pudo cargarse. Revisa el formato y el tamaño máximo indicado para esta carga.',
            'validation.attributes.pdfFirmado' => 'acta firmada',
            'validation.attributes.documentoIdentidad' => 'documento de identidad',
            'validation.attributes.documentoIdentidadSolo' => 'documento de identidad',
            'validation.custom.pdf_firmado.max' => 'El PDF firmado no debe superar los 10 MB.',
        ], 'en');
    }

    protected function configureUploadRules(): void
    {
        \Illuminate\Support\Facades\Validator::extend('pdf_max_upload', static function ($attribute, $value, $parameters): bool {
            if (! $value instanceof \Illuminate\Http\UploadedFile || ! $value->isValid()) return true;
            $pdf = strtolower($value->getClientOriginalExtension()) === 'pdf'
                || $value->getMimeType() === 'application/pdf';
            return ! $pdf || $value->getSize() <= ((int) ($parameters[0] ?? 10240)) * 1024;
        }, 'Cada PDF debe tener como máximo 10 MB.');
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
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
     * la campana interna y, con permiso expreso, los correos de autenticación.
     */
    protected function preventExternalNotificationsDuringValidation(): void
    {
        Event::listen(NotificationSending::class, static function (NotificationSending $event): ?bool {
            if (! config('hubdigital.validation_mode') || $event->channel === 'database') {
                return null;
            }

            $esAutenticacionAutorizada = config('hubdigital.allow_auth_emails_during_validation')
                && $event->channel === 'mail'
                && ($event->notification instanceof VerifyEmail || $event->notification instanceof ResetPassword);

            return $esAutenticacionAutorizada ? null : false;
        });
    }
}
