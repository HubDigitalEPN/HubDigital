<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeocodificadorInversoPort;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\Contracts\FuenteCatalogoIterator;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\CsvFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ExcelFuenteCatalogo;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FuenteCatalogoGeocodificada;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ImportarCatalogoInvertebrados;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ResultadoImport;

/**
 * Página del curador para cargar el catálogo de invertebrados desde un archivo
 * Excel (.xlsx/.xls) o CSV subido por la web. Es capa de presentación pura:
 * arma la `FuenteCatalogoIterator` adecuada según la extensión y delega TODO el
 * trabajo real al orchestrator `ImportarCatalogoInvertebrados` (el mismo que usa
 * el comando de consola), renderizando después el `ResultadoImport`.
 *
 * Ofrece dos acciones sobre el mismo archivo:
 *  - Validar (dry-run): cuenta y reporta sin escribir en la base.
 *  - Importar: persiste. Es idempotente (no duplica lo ya importado), así que
 *    reintentar tras un corte no genera basura.
 */
#[Layout('layouts.app', params: ['title' => 'Importar catálogo'])]
final class ImportarCatalogoIndex extends Component
{
    use WithFileUploads;

    /** Archivo subido (Excel o CSV). */
    public $archivo = null;

    public string $archivoNombre = '';

    /** Separador para archivos CSV (ignorado en Excel). */
    public string $delimitador = ',';

    /** Completar país/provincia/cantón/localidad faltantes por coordenadas (Nominatim). */
    public bool $geocodificar = false;

    /** Tope de coordenadas nuevas a consultar en la web (acota el tiempo de la petición). */
    private const MAX_LOOKUPS_WEB = 250;

    /** Resultado de la última corrida, ya aplanado para la vista. */
    public ?array $resultado = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    protected function rules(): array
    {
        return [
            'archivo' => 'required|file|mimes:xlsx,xls,csv,txt|max:51200',
            'delimitador' => 'required|string|max:2',
        ];
    }

    protected function messages(): array
    {
        return [
            'archivo.required' => 'Selecciona un archivo Excel o CSV para importar.',
            'archivo.mimes' => 'Solo se aceptan archivos .xlsx, .xls o .csv.',
            'archivo.max' => 'El archivo no debe superar los 50 MB.',
        ];
    }

    public function updatedArchivo(): void
    {
        $this->resultado = null;
        $this->errorMessage = null;
        $this->successMessage = null;

        if ($this->archivo === null) {
            $this->archivoNombre = '';

            return;
        }

        $this->validateOnly('archivo');
        $this->archivoNombre = $this->archivo->getClientOriginalName();
    }

    public function validar(): void
    {
        $this->ejecutar(dryRun: true);
    }

    public function importar(): void
    {
        $this->ejecutar(dryRun: false);
    }

    public function limpiar(): void
    {
        $this->reset('archivo', 'archivoNombre', 'resultado', 'errorMessage', 'successMessage');
        $this->resetValidation();
        $this->dispatch('import-limpiado');
    }

    /**
     * Ejecuta el importador sobre el archivo cargado. Envuelve todo en un try/catch
     * para que un archivo corrupto o ilegible se traduzca en un mensaje al usuario
     * en vez de una página de error.
     */
    private function ejecutar(bool $dryRun): void
    {
        $this->validate();

        // Importar un catálogo grande puede tardar y consumir memoria (parseo del
        // Excel + inserts por lotes). Se amplían los límites solo para esta petición.
        @set_time_limit(600);
        if ($this->limiteMemoriaEnBytes() < 1024 * 1024 * 1024) {
            @ini_set('memory_limit', '1024M');
        }

        try {
            $fuente = $this->construirFuente();

            // La geocodificación solo corre en la importación real (no en la
            // validación): consume un servicio con límite de tasa y no tendría
            // sentido gastar consultas en un dry-run.
            $fuenteGeo = null;
            if ($this->geocodificar && ! $dryRun) {
                $fuente = $fuenteGeo = new FuenteCatalogoGeocodificada(
                    $fuente,
                    app(GeocodificadorInversoPort::class),
                    new FilaCatalogoMapper,
                    throttleMs: 1100,
                    maxLookups: self::MAX_LOOKUPS_WEB,
                );
            }

            /** @var ImportarCatalogoInvertebrados $importer */
            $importer = app(ImportarCatalogoInvertebrados::class);

            $resultado = $importer->ejecutar(fuente: $fuente, dryRun: $dryRun);

            $this->resultado = $this->aplanar($resultado);
            $this->errorMessage = null;

            $this->successMessage = $dryRun
                ? "Validación completa: {$resultado->filasLeidas} filas leídas. Revisa el resumen y presiona «Importar al catálogo» para guardar."
                : "Importación completa: {$resultado->especimenesPersistidos} especímenes guardados en el catálogo.";

            if ($fuenteGeo !== null) {
                $this->successMessage .= " Geocodificación: {$fuenteGeo->lookupsRealizados()} coordenadas consultadas, {$fuenteGeo->filasEnriquecidas()} filas completadas.";
                if ($fuenteGeo->lookupsRealizados() >= self::MAX_LOOKUPS_WEB) {
                    $this->successMessage .= ' Se alcanzó el tope de esta pantalla; para el catálogo completo usa «php artisan inventario:importar-catalogo --geocodificar».';
                }
            }
        } catch (\Throwable $e) {
            $this->resultado = null;
            $this->successMessage = null;
            $this->errorMessage = 'No se pudo procesar el archivo: '.$e->getMessage();
        }
    }

    private function construirFuente(): FuenteCatalogoIterator
    {
        $ruta = (string) $this->archivo->getRealPath();
        $extension = strtolower((string) $this->archivo->getClientOriginalExtension());

        return in_array($extension, ['csv', 'txt'], true)
            ? new CsvFuenteCatalogo($ruta, $this->delimitador !== '' ? $this->delimitador : ',')
            : new ExcelFuenteCatalogo($ruta);
    }

    /** @return array<string, mixed> */
    private function aplanar(ResultadoImport $r): array
    {
        return [
            'filasLeidas' => $r->filasLeidas,
            'especimenesPersistidos' => $r->especimenesPersistidos,
            'muestrasCreadas' => $r->muestrasCreadas,
            'duplicadosSaltados' => $r->duplicadosSaltados,
            'marcadosParaRevision' => $r->marcadosParaRevision,
            'motivosRevision' => $r->motivosRevision,
            'erroresFatales' => $r->erroresFatales,
            'dryRun' => $r->dryRun,
            'resumen' => $r->resumenLinea(),
        ];
    }

    private function limiteMemoriaEnBytes(): int
    {
        $limite = trim((string) ini_get('memory_limit'));
        if ($limite === '' || $limite === '-1') {
            return PHP_INT_MAX;
        }

        $unidad = strtolower($limite[strlen($limite) - 1]);
        $valor = (int) $limite;

        return match ($unidad) {
            'g' => $valor * 1024 * 1024 * 1024,
            'm' => $valor * 1024 * 1024,
            'k' => $valor * 1024,
            default => (int) $limite,
        };
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.importar.index');
    }
}
