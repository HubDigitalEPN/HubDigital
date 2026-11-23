<?php

declare(strict_types=1);

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;
use Modules\GestionPrestamosRecepciones\Domain\Services\EsquemaMatriz;
use Modules\GestionPrestamosRecepciones\Infrastructure\Files\ImportadorPlantillaDepositos;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

final class EditorMatrizDepositos
{
    private const TABLA = 'recepciones.configuracion_matriz';

    public function __construct(private readonly EsquemaMatrizPort $esquema) {}

    public function importarPlantilla(string $ruta, string $huella, ImportadorPlantillaDepositos $importador): void
    {
        $usuario = $this->autorizar();
        $preview = $importador->leer($ruta, $this->esquema->leer());

        DB::transaction(function () use ($preview, $huella, $usuario): void {
            $this->bloquear($huella);
            $this->guardarDefinicion($preview['definicion'], $usuario);
        });
    }

    public function guardarFila(?string $campoOriginal, array $datos, string $huella): void
    {
        $usuario = $this->autorizar();
        $datos = array_map(static fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $datos);
        if ($campoOriginal !== null) {
            $datos['campo'] = $campoOriginal;
        }
        $columna = Validator::make($datos, [
            'campo' => ['required', 'string', 'max:160'],
            'orden' => ['sometimes', 'required', 'integer', 'min:1'],
            'cabecera' => ['required', 'string', 'max:160'],
            'alias' => ['nullable', 'string', 'max:2000'],
            'requisito' => ['required', 'in:obligatorio,recomendado,opcional,interno,predeterminado'],
            'requisito_guia' => ['nullable', 'in:obligatorio,recomendado,opcional'],
            'formato' => ['required', 'in:texto,fecha'],
            'descripcion' => ['nullable', 'string', 'max:4000'],
            'excepcion_campo' => ['nullable', 'string', 'max:160', 'required_with:excepcion_valor'],
            'excepcion_valor' => ['nullable', 'string', 'max:160', 'required_with:excepcion_campo'],
            'plantilla_depositante' => ['required', 'boolean'],
        ])->validate();
        if (($columna['requisito_guia'] ?? null) === '') {
            $columna['requisito_guia'] = null;
        }

        DB::transaction(function () use ($campoOriginal, $columna, $huella, $usuario): void {
            $definicion = $this->bloquear($huella);
            $columnas = $definicion['columnas'];
            $indice = $campoOriginal === null ? count($columnas) : $this->indice($columnas, $campoOriginal);
            $destino = isset($columna['orden']) ? (int) $columna['orden'] - 1 : $indice;
            unset($columna['orden']);
            if ($destino >= count($columnas) + (int) ($campoOriginal === null)) {
                throw new DomainException('El orden debe corresponder a una posición de la matriz.');
            }

            $anterior = $columnas[$indice] ?? [];
            $alias = explode(',', $columna['alias'] ?? '');
            if (isset($anterior['cabecera']) && $anterior['cabecera'] !== $columna['cabecera']) {
                $alias[] = $anterior['cabecera'];
            }
            $columna['alias'] = array_values(array_unique(array_filter(array_map('trim', $alias),
                static fn (string $valor): bool => $valor !== '')));
            $columna['plantilla_depositante'] = (bool) $columna['plantilla_depositante'];
            foreach (['descripcion', 'excepcion_campo', 'excepcion_valor'] as $campoOpcional) {
                $columna[$campoOpcional] ??= '';
            }
            $this->esquema->guardarColumna($columna + $anterior, $campoOriginal, $usuario);

            if ($destino !== $indice) {
                $definicion = $this->esquema->leer();
                [$movida] = array_splice($definicion['columnas'], $indice, 1);
                array_splice($definicion['columnas'], $destino, 0, [$movida]);
                $this->guardarDefinicion($definicion, $usuario);
            }
        });
    }

    public function eliminarFila(string $campo, string $huella): void
    {
        $usuario = $this->autorizar();
        DB::transaction(function () use ($campo, $huella, $usuario): void {
            $definicion = $this->bloquear($huella);
            $columnas = $definicion['columnas'];
            $indice = $this->indice($columnas, $campo);
            if (in_array($campo, $this->protegidas($definicion), true)) {
                throw new DomainException('Esta columna es obligatoria o se usa en la plantilla, una excepción o la identificación de la matriz.');
            }
            if (DB::table('recepciones.matrices_especies')->whereJsonContains('campos_dwc_presentes', [$campo])->exists()
                || DB::table('recepciones.registros_especimen')->whereRaw('jsonb_exists(datos_dwc, ?)', [$campo])->exists()
                || DB::table('recepciones.contenido_recepcion')->whereRaw('jsonb_exists(datos_dwc, ?)', [$campo])->exists()) {
                throw new DomainException('Esta columna se usa en registros de depósitos.');
            }

            array_splice($definicion['columnas'], $indice, 1);
            $this->guardarDefinicion($definicion, $usuario);
        });
    }

    private function autorizar(): string
    {
        $usuario = auth()->user();
        $usuario?->unsetRelation('roles');
        abort_unless($usuario?->esCurador(), 403);

        return (string) $usuario->getKey();
    }

    private function bloquear(string $huella): array
    {
        $fila = DB::table(self::TABLA)->where('clave', 'depositos')->lockForUpdate()->first();
        if ($fila === null) {
            throw new DomainException('La configuración de la matriz de depósitos todavía no está disponible.');
        }
        $definicion = json_decode($fila->definicion, true, 512, JSON_THROW_ON_ERROR);
        if (!hash_equals(ConfiguracionPlantillaCatalogo::huella($definicion), $huella)) {
            throw new DomainException('Otra persona cambió la matriz. Vuelve a abrir la columna antes de guardar.');
        }

        return $definicion;
    }

    private function indice(array $columnas, string $campo): int
    {
        $indice = array_search($campo, array_column($columnas, 'campo'), true);
        if ($indice === false) {
            throw new DomainException('La columna cambió. Vuelve a abrir la matriz.');
        }

        return $indice;
    }

    public function protegidas(array $definicion): array
    {
        $columnas = $definicion['columnas'];
        $obligatorias = array_filter($columnas, static fn (array $columna): bool => $columna['requisito'] === 'obligatorio');

        return $this->camposProtegidos($definicion['identificacion'] ?? [],
            array_column($obligatorias, 'campo'), array_column($columnas, 'excepcion_campo'));
    }

    public function camposProtegidos(array $identificacion, array $obligatorias, array $excepciones): array
    {
        $claves = [...array_merge(...array_values($identificacion)), ...$obligatorias, ...array_filter($excepciones)];
        foreach (['matriz-deposito-v2.json', 'matriz-depositantes-v3.json'] as $archivo) {
            $plantilla = json_decode(file_get_contents(base_path('Modules/GestionPrestamosRecepciones/database/data/'.$archivo)),
                true, 512, JSON_THROW_ON_ERROR);
            $claves = [...$claves, ...array_column($plantilla['columnas'], 'campo')];
        }

        return array_values(array_unique($claves));
    }

    private function guardarDefinicion(array $definicion, string $usuario): void
    {
        EsquemaMatriz::mapa($definicion['columnas']);
        DB::table(self::TABLA)->where('clave', 'depositos')->update([
            'definicion' => json_encode($definicion, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'actualizado_por' => $usuario,
            'updated_at' => now(),
        ]);
    }
}
