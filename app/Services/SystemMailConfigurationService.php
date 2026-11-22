<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemMailConfiguration;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SystemMailConfigurationService
{
    public function stored(): ?SystemMailConfiguration
    {
        if (! Schema::hasTable('usuarios.configuracion_correo')) {
            return null;
        }

        return SystemMailConfiguration::query()->find(1);
    }

    public function applyStored(): void
    {
        $configuration = $this->stored();
        if ($configuration !== null) {
            $this->apply($configuration);
        }
    }

    /** Importación inicial: después la base es la única fuente administrable. */
    public function importEnvironmentIfMissing(): void
    {
        // El candidato OCI puede arrancar antes de aplicar esta migracion.
        // En ese intervalo SMTP debe seguir usando el entorno sin intentar
        // insertar en una tabla que aun no existe.
        if (app()->environment('testing')
            || ! Schema::hasTable('usuarios.configuracion_correo')
            || $this->stored() !== null
            || (Schema::hasTable('usuarios.estado_configuracion_correo')
                && DB::table('usuarios.estado_configuracion_correo')->where('id', 1)->exists())) {
            return;
        }

        $password = (string) config('mail.mailers.smtp.password');
        $username = (string) config('mail.mailers.smtp.username');
        $fromAddress = (string) config('mail.from.address');

        if ($password === '' || $username === '' || $fromAddress === '') {
            return;
        }

        SystemMailConfiguration::query()->firstOrCreate(
            ['id' => 1],
            [
                'host' => (string) config('mail.mailers.smtp.host', 'smtp.zoho.com'),
                'port' => (int) config('mail.mailers.smtp.port', 587),
                'username' => $username,
                'password' => $password,
                'from_address' => $fromAddress,
                'from_name' => (string) config('mail.from.name', 'HubDigital'),
                'updated_by' => null,
            ],
        );
    }

    public function registrarEliminacion(): void
    {
        DB::table('usuarios.estado_configuracion_correo')->insertOrIgnore(['id' => 1]);
    }

    /**
     * @param  array{host:string,port:int,username:string,from_address:string,from_name:string}  $data
     */
    public function save(array $data, #[\SensitiveParameter] string $password, User $actor): SystemMailConfiguration
    {
        try {
            $effectivePassword = $password !== ''
                ? $password
                : (string) ($this->stored()?->password ?? config('mail.mailers.smtp.password'));
        } catch (DecryptException) {
            throw new \DomainException('Ingresa una nueva contraseña de aplicación SMTP para reemplazar la guardada.');
        }

        if ($effectivePassword === '') {
            throw new \DomainException('Ingresa la contraseña de aplicación SMTP.');
        }

        SystemMailConfiguration::query()->upsert(
            [['id' => 1, ...$data, 'password' => Crypt::encryptString($effectivePassword), 'updated_by' => $actor->getKey()]],
            ['id'],
            [...array_keys($data), 'password', 'updated_by'],
        );
        $configuration = SystemMailConfiguration::query()->findOrFail(1);

        $this->apply($configuration);

        return $configuration;
    }

    public function apply(SystemMailConfiguration $configuration): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $configuration->host,
            'mail.mailers.smtp.port' => $configuration->port,
            'mail.mailers.smtp.username' => $configuration->username,
            'mail.mailers.smtp.password' => $configuration->password,
            'mail.from.address' => $configuration->from_address,
            'mail.from.name' => $configuration->from_name,
        ]);
    }
}
