<?php

namespace App\Support;

use DateTimeImmutable;
use Throwable;

/** Identidad pública del artefacto activo, sin consultar Git ni el entorno. */
final class InformacionRelease
{
    private const REPOSITORIO = 'https://github.com/HubDigitalEPN/HubDigital';

    private string $directorio;

    public function __construct(?string $directorio = null)
    {
        $this->directorio = $directorio ?? base_path();
    }

    public function obtener(): array
    {
        $fuente = $this->leer('SOURCE-METADATA.json');
        $commit = $fuente['git_commit'] ?? null;
        $disponible = ($fuente['format_version'] ?? null) === 1
            && ($fuente['repository'] ?? null) === self::REPOSITORIO
            && ($fuente['git_branch'] ?? null) === 'main'
            && is_string($commit) && preg_match('/\A[0-9a-f]{40}\z/', $commit) === 1;

        if (! $disponible) {
            return ['disponible' => false, 'mensaje' => 'La identidad de esta versión todavía no está disponible.'];
        }

        $activacion = $this->leer('RELEASE-STATUS.json');
        $mismaRelease = ($activacion['git_commit'] ?? null) === $commit
            && is_string($activacion['release_id'] ?? null)
            && preg_match('/\A[0-9a-f]{16}\z/', $activacion['release_id']) === 1;

        // Lista cerrada: los metadatos completos y los secretos nunca se publican.
        return [
            'disponible' => true,
            'version' => substr($commit, 0, 8),
            'commit' => $commit,
            'repositorio' => self::REPOSITORIO,
            'compilado_en' => $this->fechaUtc($fuente['built_at_utc'] ?? null),
            'desplegado_en' => $mismaRelease ? $this->fechaUtc($activacion['activated_at_utc'] ?? null) : null,
        ];
    }

    private function leer(string $archivo): array
    {
        $ruta = $this->directorio.DIRECTORY_SEPARATOR.$archivo;
        if (! is_file($ruta) || ! is_readable($ruta) || filesize($ruta) > 16384) {
            return [];
        }

        try {
            $texto = file_get_contents($ruta);
            $valor = $texto === false ? null : json_decode($texto, true, 8, JSON_THROW_ON_ERROR);

            return is_array($valor) ? $valor : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function fechaUtc(mixed $fecha): ?string
    {
        if (! is_string($fecha) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $fecha) !== 1) {
            return null;
        }

        $valor = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $fecha);

        return $valor !== false && $valor->format('Y-m-d\TH:i:s\Z') === $fecha ? $fecha : null;
    }
}
