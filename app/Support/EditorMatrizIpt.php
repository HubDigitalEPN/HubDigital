<?php

declare(strict_types=1);

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\Validator;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

final class EditorMatrizIpt
{
    public function __construct(private readonly ConfiguracionIpt $configuracion) {}

    public function guardarFila(?string $campoOriginal, array $datos, string $huella): void
    {
        $definicion = $this->leerVigente($huella);
        $columnas = $definicion['columnas'];
        $campos = array_column($columnas, 'campo');
        $ordenMaximo = count($columnas) + ($campoOriginal === null ? 1 : 0);

        $datos['campo'] = $campoOriginal ?? ($datos['campo'] ?? null);
        $datos = Validator::make($datos, [
            'campo' => ['required', 'string'],
            'orden' => ['required', 'integer', 'between:1,'.$ordenMaximo],
            'origen' => ['nullable', 'string'],
            'formato' => ['required', 'string'],
            'ancho' => ['required', 'integer', 'between:5,80'],
        ], [
            'campo.required' => 'Selecciona un término Darwin Core.',
            'orden.between' => 'El orden debe estar entre 1 y '.$ordenMaximo.'.',
            'ancho.between' => 'El ancho debe estar entre 5 y 80.',
        ])->validate();

        $fila = [
            'campo' => $datos['campo'],
            'origen' => $datos['origen'] ?? '',
            'formato' => $datos['formato'],
            'ancho' => (int) $datos['ancho'],
        ];
        if ($campoOriginal !== null) {
            $indice = $this->indice($campos, $campoOriginal);
            $fila = array_replace($columnas[$indice], $fila);
            array_splice($columnas, $indice, 1);
        } elseif (in_array($fila['campo'], $campos, true)) {
            throw new DomainException('La columna '.$fila['campo'].' ya está incluida.');
        }

        array_splice($columnas, (int) $datos['orden'] - 1, 0, [$fila]);
        $definicion['columnas'] = $columnas;
        $this->guardarDefinicion($definicion, $huella);
    }

    public function eliminarFila(string $campo, string $huella): void
    {
        $definicion = $this->leerVigente($huella);
        $indice = $this->indice(array_column($definicion['columnas'], 'campo'), $campo);
        array_splice($definicion['columnas'], $indice, 1);
        $this->guardarDefinicion($definicion, $huella);
    }

    public function restaurar(string $huella): void
    {
        $this->leerVigente($huella);
        $this->guardarDefinicion(ConfiguracionIpt::predeterminada(), $huella);
    }

    private function leerVigente(string $huella): array
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        $definicion = $this->configuracion->leer();
        if (!hash_equals(ConfiguracionPlantillaCatalogo::huella($definicion), $huella)) {
            throw new DomainException('Otro administrador cambió el formato. Vuelve a abrir la matriz antes de guardar.');
        }

        return $definicion;
    }

    private function indice(array $campos, string $campo): int
    {
        $indice = array_search($campo, $campos, true);
        if ($indice === false) {
            throw new DomainException('La columna '.$campo.' ya no existe en el formato.');
        }

        return $indice;
    }

    private function guardarDefinicion(array $definicion, string $huella): void
    {
        $definicion['fuente'] = 'configuracion-administrador';
        $this->configuracion->guardar($definicion, $huella);
    }
}
