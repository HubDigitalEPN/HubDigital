<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarCodigoQr\GenerarCodigoQrInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerCodigoQr\ObtenerCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerCodigoQr\ObtenerCodigoQrInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\GeneraSvgQr;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Estación de etiquetado QR: busca un espécimen por su identificador y genera
 * (o reutiliza) su código QR físico para imprimirlo o descargarlo. Es el punto
 * de entrada descubrible del etiquetado; la misma acción existe por fila en el
 * catálogo de Especímenes.
 */
#[Layout('layouts.app', params: ['title' => 'Etiquetado QR'])]
final class EtiquetadoQrIndex extends Component
{
    use GeneraSvgQr;
    use TraduceErroresPersistencia;

    public string $busqueda = '';

    /** @var array<int, array{id:string, codigoCatalogo:string, taxonNombre:?string, localidad:string}> */
    public array $resultados = [];

    public bool $buscado = false;

    public ?string $qrUrl = null;

    public ?string $qrSvg = null;

    public ?string $qrEspecimenCodigo = null;

    public ?string $errorMessage = null;

    public function buscar(BuscarEspecimenesHandler $handler): void
    {
        $texto = trim($this->busqueda);
        if ($texto === '') {
            $this->errorMessage = 'Escribe un código de catálogo, occurrenceID o número de catálogo.';

            return;
        }

        try {
            // Prueba por código de catálogo, luego occurrenceID, luego catalogNumber.
            $items = $this->buscarPorCampo($handler, 'codigoCatalogo', $texto)
                ?: $this->buscarPorCampo($handler, 'occurrenceId', $texto)
                ?: $this->buscarPorCampo($handler, 'catalogNumber', $texto);

            $this->resultados = array_map(fn ($e) => [
                'id' => $e['id'],
                'codigoCatalogo' => $e['codigoCatalogo'],
                'taxonNombre' => $e['taxonNombre'],
                'localidad' => $e['localidad'],
            ], $items);
            $this->buscado = true;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /** @return list<array<string, mixed>> */
    private function buscarPorCampo(BuscarEspecimenesHandler $handler, string $campo, string $valor): array
    {
        return $handler->handle(new BuscarEspecimenesInput(
            codigoCatalogo: $campo === 'codigoCatalogo' ? $valor : null,
            occurrenceId: $campo === 'occurrenceId' ? $valor : null,
            catalogNumber: $campo === 'catalogNumber' ? $valor : null,
            page: 1,
            perPage: 25,
        ))->items;
    }

    public function mostrarQr(
        string $id,
        ObtenerCodigoQrHandler $obtenerHandler,
        GenerarCodigoQrHandler $generarHandler,
    ): void {
        try {
            $existente = $obtenerHandler->handle(new ObtenerCodigoQrInput(especimenId: $id));
            $payload = $existente->existe
                ? $existente->payload
                : $generarHandler->handle(new GenerarCodigoQrInput(especimenId: $id))->payload;

            $this->qrUrl = route('inventario.qr.resolver', ['payload' => $payload]);
            $this->qrSvg = $this->generarSvgQr($this->qrUrl);
            $row = collect($this->resultados)->firstWhere('id', $id);
            $this->qrEspecimenCodigo = $row['codigoCatalogo'] ?? $id;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function cerrarQr(): void
    {
        $this->qrUrl = null;
        $this->qrSvg = null;
        $this->qrEspecimenCodigo = null;
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.etiquetas.index');
    }
}
