<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use App\Support\AccesoCatalogo;
use DomainException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class EditorMatrizImportacion
{
    public function __construct(private readonly ConfiguracionPlantillaCatalogo $configuracion) {}

    public function guardarFila(?string $campoOriginal, array $datos, string $huella): void
    {
        $definicion = $this->leerConHuella($huella);
        $columnas = $definicion['columnas'];
        $indice = $campoOriginal === null ? null : $this->indice($columnas, $campoOriginal);
        $limite = count($columnas) + ($indice === null ? 1 : 0);
        $valores = Validator::make($datos, [
            'cabecera' => ['required', 'string', 'max:160'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'requisito' => ['required', 'in:obligatorio,recomendado,opcional'],
            'formato' => ['required', 'in:texto,fecha'],
            'plantilla_catalogo' => ['sometimes', 'boolean'],
            'requisito_guia' => ['nullable', 'in:obligatorio,recomendado,opcional,interno,predeterminado'],
            'orden' => ['nullable', 'integer', 'min:1', 'max:'.$limite],
            'alias' => ['nullable', 'string', 'max:5000'],
            'campo' => ['nullable', 'string', 'max:160'],
        ], [], [
            'cabecera' => 'cabecera Excel',
            'descripcion' => 'descripción y ejemplo',
            'requisito_guia' => 'requisito en la guía',
        ])->validate();

        $fila = $indice === null ? $this->nuevaFila($valores['campo'] ?? '', $columnas) : $columnas[$indice];
        $cabeceraAnterior = $fila['cabecera'] ?? '';
        $fila['cabecera'] = trim($valores['cabecera']);
        $fila['descripcion'] = trim($valores['descripcion']);
        $fila['requisito'] = $valores['requisito'];
        $fila['formato'] = $valores['formato'];
        if (array_key_exists('plantilla_catalogo', $valores)) {
            unset($fila['plantilla_catalogo']);
            if (! (bool) $valores['plantilla_catalogo']) {
                $fila['plantilla_catalogo'] = false;
            }
        }
        $fila['alias'] = $this->alias($valores, $fila['alias'] ?? [], $cabeceraAnterior, $fila['cabecera']);
        if (array_key_exists('requisito_guia', $valores)) {
            unset($fila['requisito_guia']);
            if (filled($valores['requisito_guia'])) {
                $fila['requisito_guia'] = $valores['requisito_guia'];
            }
        }

        if ($indice !== null) {
            array_splice($columnas, $indice, 1);
        }
        $posicion = isset($valores['orden']) ? (int) $valores['orden'] - 1 : ($indice ?? count($columnas));
        array_splice($columnas, $posicion, 0, [$fila]);
        $definicion['columnas'] = $columnas;

        $this->configuracion->guardar($definicion, $huella, (string) auth()->id());
    }

    public function eliminarFila(string $campo, string $huella): void
    {
        $definicion = $this->leerConHuella($huella);
        $indice = $this->indice($definicion['columnas'], $campo);
        array_splice($definicion['columnas'], $indice, 1);
        $this->configuracion->guardar($definicion, $huella, (string) auth()->id());
    }

    private function leerConHuella(string $huella): array
    {
        AccesoCatalogo::autorizar();
        $definicion = $this->configuracion->leer();
        if (! hash_equals(ConfiguracionPlantillaCatalogo::huella($definicion), $huella)) {
            throw new DomainException('La plantilla cambió mientras la editabas. Abre la versión actual y vuelve a aplicar tus ajustes.');
        }

        return $definicion;
    }

    private function indice(array $columnas, string $campo): int
    {
        $indice = array_search($campo, array_column($columnas, 'campo'), true);
        if ($indice === false) {
            throw new DomainException('La columna ya no está en la plantilla. Abre la versión actual antes de continuar.');
        }

        return $indice;
    }

    private function nuevaFila(string $campo, array $columnas): array
    {
        if ($campo === '') {
            abort_unless(auth()->user()?->esAdministrador(), 403, 'Solo el administrador puede crear columnas personalizadas.');

            return ['campo' => 'extra_'.str_replace('-', '', (string) Str::uuid()), 'personalizada' => true];
        }

        $estandar = array_column(ConfiguracionPlantillaCatalogo::predeterminada()['columnas'], null, 'campo');
        if (! isset($estandar[$campo]) || in_array($campo, array_column($columnas, 'campo'), true)) {
            throw new DomainException('Selecciona un campo estándar que todavía no figure en la plantilla.');
        }

        return $estandar[$campo];
    }

    private function alias(array $valores, array $existentes, string $cabeceraAnterior, string $cabecera): array
    {
        $alias = array_key_exists('alias', $valores) ? preg_split('/\R/u', $valores['alias'] ?? '') : $existentes;
        if ($cabeceraAnterior !== '' && $cabeceraAnterior !== $cabecera) {
            $alias[] = $cabeceraAnterior;
        }
        $alias = array_values(array_unique(array_filter(array_map(trim(...), $alias), static fn (string $valor): bool => $valor !== '')));
        foreach ($alias as $valor) {
            if (mb_strlen($valor) > 160 || str_starts_with($valor, '=')) {
                throw new DomainException('Cada alias debe contener hasta 160 caracteres y no puede ser una fórmula.');
            }
        }

        return $alias;
    }
}
