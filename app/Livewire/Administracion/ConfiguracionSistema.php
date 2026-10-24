<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Mail\SystemMailTest;
use App\Services\SystemMailConfigurationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Configuración del sistema')]
final class ConfiguracionSistema extends Component
{
    public bool $descargasAutoservicio = false;

    public function guardarDescargas(): void
    {
        $this->autorizar();
        \Illuminate\Support\Facades\DB::table('usuarios.configuracion_catalogo')->updateOrInsert(
            ['clave' => 'descargas_autoservicio'],
            ['habilitado' => $this->descargasAutoservicio, 'actualizado_por' => auth()->id(), 'updated_at' => now()],
        );
        session()->flash('configuracion-descargas', 'Permiso de descarga actualizado.');
    }
    public string $smtpHost = 'smtp.zoho.com';

    public int $smtpPort = 587;

    public string $smtpUsername = '';

    public string $fromAddress = '';

    public string $fromName = 'HubDigital';

    public string $destinatarioPrueba = '';

    public bool $configuracionGuardada = false;

    public function boot(): void
    {
        request()->attributes->set('administracion_sensible', true);
        $this->autorizar();
    }

    public function mount(SystemMailConfigurationService $service): void
    {
        $this->descargasAutoservicio = \App\Support\DescargasCatalogo::publicasHabilitadas();
        $configuration = $service->stored();
        $this->configuracionGuardada = $configuration !== null;
        $this->smtpHost = (string) ($configuration?->host ?? config('mail.mailers.smtp.host', 'smtp.zoho.com'));
        $this->smtpPort = (int) ($configuration?->port ?? config('mail.mailers.smtp.port', 587));
        $this->smtpUsername = (string) ($configuration?->username ?? config('mail.mailers.smtp.username', ''));
        $this->fromAddress = (string) ($configuration?->from_address ?? config('mail.from.address', ''));
        $this->fromName = (string) ($configuration?->from_name ?? config('mail.from.name', 'HubDigital'));
        $this->destinatarioPrueba = (string) auth()->user()?->email;
    }

    public function guardar(
        SystemMailConfigurationService $service,
        #[\SensitiveParameter] string $password,
    ): void {
        $this->autorizar();

        $validated = $this->validate([
            'smtpHost' => ['required', 'string', 'max:255', 'regex:/^(?=.{1,255}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i'],
            'smtpPort' => ['required', 'integer', 'between:1,65535'],
            'smtpUsername' => ['required', 'email:rfc', 'max:255'],
            'fromAddress' => ['required', 'email:rfc', 'max:255'],
            'fromName' => ['required', 'string', 'max:255'],
        ]);

        Validator::make(['password' => $password], [
            'password' => [$this->configuracionGuardada ? 'nullable' : 'required', 'string', 'max:255'],
        ])->validate();

        try {
            $service->save([
                'host' => strtolower(trim($validated['smtpHost'])),
                'port' => (int) $validated['smtpPort'],
                'username' => strtolower(trim($validated['smtpUsername'])),
                'from_address' => strtolower(trim($validated['fromAddress'])),
                'from_name' => trim($validated['fromName']),
            ], $password, auth()->user());
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['smtpPassword' => $exception->getMessage()]);
        }

        $this->configuracionGuardada = true;
        session()->flash('configuracion-correo', 'Configuración SMTP guardada. La contraseña quedó cifrada y no se volverá a mostrar.');
    }

    public function probar(SystemMailConfigurationService $service): void
    {
        $this->autorizar();
        $this->validate([
            'destinatarioPrueba' => ['required', 'email:rfc', 'max:255'],
        ]);

        $configuration = $service->stored();
        if ($configuration === null) {
            $this->addError('destinatarioPrueba', 'Guarda primero la configuración SMTP.');

            return;
        }

        try {
            $service->apply($configuration);
            Mail::to($this->destinatarioPrueba)->send(new SystemMailTest);
        } catch (Throwable $exception) {
            Log::warning('El administrador no pudo completar la prueba SMTP.', [
                'exception_type' => $exception::class,
            ]);
            $this->addError('destinatarioPrueba', 'El proveedor rechazó la prueba. Revisa usuario, contraseña, servidor y puerto.');

            return;
        }

        session()->flash('prueba-correo', "Zoho aceptó el correo de prueba para {$this->destinatarioPrueba}.");
    }

    public function render(): View
    {
        return view('livewire.administracion.configuracion-sistema');
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
    }
}
