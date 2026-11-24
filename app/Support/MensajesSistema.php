<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

final class MensajesSistema
{
    public const GRUPOS_IDIOMA = ['auth' => 'Inicio de sesión', 'passwords' => 'Recuperación de contraseña', 'validation' => 'Validaciones'];

    private ?array $guardados = null;

    public static function definiciones(): array
    {
        $definiciones = [];
        foreach (config('mensajes-sistema', []) as $clave => [$uso, $texto]) {
            $definiciones[$clave] = ['Importar catálogo · '.$uso, $texto];
        }
        foreach (['wizard-copy' => 'Formulario de depósitos', 'portal-copy' => 'Portal público'] as $grupo => $uso) {
            foreach (Arr::dot(config($grupo, [])) as $clave => $texto) {
                if (is_string($texto) && preg_match('/\pL/u', $texto) && !preg_match('/@|^(?:https?:|\/)/i', $texto)) {
                    $definiciones[$grupo.'.'.$clave] = [$uso, $texto];
                }
            }
        }
        foreach (self::GRUPOS_IDIOMA as $grupo => $uso) {
            foreach (Arr::dot(require lang_path('es/'.$grupo.'.php')) as $clave => $texto) {
                if (is_string($texto) && !str_starts_with($clave, 'attributes.')) {
                    $definiciones['idioma.'.$grupo.'.'.$clave] = [$uso, $texto];
                }
            }
        }

        return $definiciones;
    }

    public static function marcadores(string $texto): array
    {
        preg_match_all('/\{[a-z][a-z0-9_]*\}|(?<![\w:]):[a-z][a-z0-9_]*(?![\w:])/i', $texto, $coincidencias);
        $marcadores = array_unique($coincidencias[0]);
        sort($marcadores);

        return $marcadores;
    }

    public static function consulta(): Builder
    {
        return DB::table('usuarios.mensajes_sistema');
    }

    public function guardados(): array
    {
        try {
            if ($this->guardados === null && !Schema::hasTable('usuarios.mensajes_sistema')) {
                return $this->guardados = [];
            }
            return $this->guardados ??= self::consulta()->pluck('contenido', 'clave')->all();
        } catch (QueryException $error) {
            // Una incidencia con los textos editables no interrumpe la importación.
            if ((string) $error->getCode() !== '42P01') {
                report($error);
            }
            return $this->guardados = [];
        }
    }

    public function personalizado(string $clave): ?string
    {
        return $this->guardados()[$clave] ?? null;
    }

    public function texto(string $clave, array $datos = []): string
    {
        $original = config('mensajes-sistema')[$clave][1];
        $contenido = $this->personalizado($clave) ?? $original;
        $reemplazos = [];
        foreach ($datos as $nombre => $valor) {
            $reemplazos['{'.$nombre.'}'] = (string) $valor;
        }

        return strtr($contenido, $reemplazos);
    }
}
