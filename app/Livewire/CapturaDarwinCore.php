<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\ColumnasCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;
use Modules\InventarioGestionColeccion\Presentation\Support\ConfiguracionCapturaCatalogo;

/** Captura limitada: lista blanca recalculada en servidor en cada guardado. */
#[Layout('layouts.app', params: ['title' => 'Captura Darwin Core'])]
final class CapturaDarwinCore extends Component
{
    #[Locked] public ?string $id = null;
    public string $busqueda = '';
    public array $datos = [];
    public string $mensaje = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->tieneAlgunRol(RolUsuario::PASANTE, RolUsuario::CURADOR), 403);
    }

    public function campos(): array
    {
        $plantilla = array_column(app(ConfiguracionPlantillaCatalogo::class)->leer()['columnas'], null, 'campo');
        $configuracion = app(ConfiguracionCapturaCatalogo::class)->campos();
        $columnas = Schema::getColumnListing('taxonomia.especimenes');
        $campos = [];
        foreach (RegistroColumnasEspecimen::todas() as $col) {
            if (! $col['nombreDwC'] || in_array($col['clave'], ['occurrenceId', 'catalogNumber', 'cardexLiquidCollectionCode'], true)) continue;
            $config = $plantilla[ColumnasCatalogo::campo($col['clave'])] ?? null;
            if ($config && (($config['requisito_guia'] ?? '') === 'interno' || $config['requisito'] === 'uso-interno' || str_contains(mb_strtolower($config['cabecera']), 'uso interno'))) continue;
            $db = $col['clave'] === 'taxonNombre' ? 'taxon_verbatim' : Str::snake($col['clave']);
            if (!in_array($db, $columnas, true)) continue;
            // Dos nombres de presentación no crean dos editores para el mismo dato.
            if (in_array($db, array_column($campos, 'db'), true)) continue;
            $ajuste = $configuracion[$col['clave']] ?? null;
            $campos[$col['clave']] = ['db' => $db, 'etiqueta' => $col['nombreDwC'].' · '.($ajuste['etiqueta'] ?? $col['etiqueta']),
                'obligatorio' => $col['clave'] === 'taxonNombre' || ($ajuste['obligatorio'] ?? false)];
        }
        return $campos;
    }

    public function nuevo(): void
    {
        $this->reset('id', 'datos', 'mensaje');
        $this->resetValidation();
        foreach ($this->campos() as $clave => $campo) $this->datos[$clave] = '';
        if (array_key_exists('fechaColecta', $this->datos)) {
            $this->datos['fechaColecta'] = now('America/Guayaquil')->toDateString();
        }
    }

    public function seleccionar(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);
        $fila = DB::table('taxonomia.especimenes')->where('id', $id)->first();
        abort_unless($fila, 404);
        $this->id = $id;
        $this->datos = [];
        foreach ($this->campos() as $clave => $campo) $this->datos[$clave] = (string) ($fila->{$campo['db']} ?? '');
        $this->mensaje = '';
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $campos = $this->campos();
        abort_if(array_diff(array_keys($this->datos), array_keys($campos)) !== [], 403, 'El formulario contiene campos reservados.');
        $reglas = [];
        foreach ($campos as $clave => $campo) $reglas['datos.'.$clave] = 'nullable|string|max:'.(RegistroColumnasEspecimen::campoEditable($clave)['maximo'] ?? 255);
        $reglas['datos.decimalLatitude'] = 'nullable|numeric|between:-90,90';
        $reglas['datos.decimalLongitude'] = 'nullable|numeric|between:-180,180';
        foreach (['elevationMinM', 'elevationMaxM'] as $clave) $reglas['datos.'.$clave] = 'nullable|numeric';
        $reglas['datos.individualCount'] = 'nullable|integer|min:0';
        $reglas['datos.fechaColecta'] = 'nullable|date_format:Y-m-d';
        if (!$this->id) {
            foreach ($campos as $clave => $campo) {
                if ($campo['obligatorio']) {
                    $reglas['datos.'.$clave] = preg_replace('/^nullable\|/', 'required|', $reglas['datos.'.$clave]);
                }
            }
        }
        $this->validate($reglas);
        DB::transaction(function () use ($campos): void {
            $id = $this->id ?: (string) Str::uuid();
            $fila = $this->id ? DB::table('taxonomia.especimenes')->where('id', $id)->lockForUpdate()->first() : null;
            if ($this->id) abort_unless($fila, 404);
            $valores = [];
            $historial = [];
            foreach ($campos as $clave => $campo) {
                if (!array_key_exists($clave, $this->datos)) continue;
                $valor = trim((string) $this->datos[$clave]);
                $valor = $valor === '' ? null : $valor;
                $previo = $fila?->{$campo['db']} ?? null;
                if ((string) $previo === (string) $valor) continue;
                $valores[$campo['db']] = $valor;
                $historial[] = [
                    'especimen_id' => $id, 'campo' => $clave, 'anterior' => $previo, 'nuevo' => $valor,
                    'actualizado_por' => auth()->id(), 'created_at' => now(),
                ];
            }
            if (array_key_exists('taxon_verbatim', $valores)) {
                $valores['taxon_id'] = DB::table('taxonomia.taxones')->whereRaw('lower(nombre_cientifico) = ?', [mb_strtolower((string) $valores['taxon_verbatim'])])->value('id');
            }
            if (!$this->id) {
                // Identificadores y estado interno los asigna exclusivamente el servidor.
                DB::table('taxonomia.especimenes')->insert([
                    'id' => $id, 'codigo_catalogo' => 'CAP-'.strtoupper(str_replace('-', '', $id)),
                    'occurrence_id' => 'urn:uuid:'.$id, 'localidad' => $valores['locality_name'] ?? '',
                    'fecha_colecta' => $valores['fecha_colecta'] ?? null, 'colector' => $valores['colector'] ?? '',
                    'estado' => 'disponible', 'estado_revision' => 'pendiente',
                    'motivo_revision' => 'Registro de captura Darwin Core pendiente de revisión curatorial.',
                    'created_at' => now(), 'updated_at' => now(), ...$valores,
                ]);
            } elseif ($valores !== []) {
                DB::table('taxonomia.especimenes')->where('id', $id)->update([...$valores, 'updated_at' => now()]);
            }
            if ($historial !== []) DB::table('taxonomia.historial_columnas_especimen')->insert($historial);
            $this->id = $id;
        });
        $this->mensaje = 'Datos Darwin Core guardados. Los campos reservados se conservaron.';
    }

    public function render()
    {
        $buscar = trim($this->busqueda);
        $filas = DB::table('taxonomia.especimenes')->when($buscar !== '', fn ($q) => $q->where(function ($q) use ($buscar) {
            $q->where('codigo_catalogo', 'ilike', '%'.$buscar.'%')->orWhere('taxon_verbatim', 'ilike', '%'.$buscar.'%')->orWhere('colector', 'ilike', '%'.$buscar.'%');
        }))->orderByDesc('updated_at')->limit(30)->get(['id', 'codigo_catalogo', 'taxon_verbatim']);
        return view('livewire.captura-darwin-core', ['campos' => $this->campos(), 'filas' => $filas]);
    }
}
