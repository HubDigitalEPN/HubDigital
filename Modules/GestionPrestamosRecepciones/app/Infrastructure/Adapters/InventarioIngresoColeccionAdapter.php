<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ResultadoIngresoColeccion;
use Modules\GestionPrestamosRecepciones\Application\Ports\ResumenIngresoColeccion;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Traspasa los especímenes de un lote recibido al módulo InventarioGestionColeccion.
 *
 * Es el único punto que conoce los dos bounded contexts, igual que el
 * {@see InventarioGestionColeccionCatalogoCuraduriaAdapter} lo es en sentido lectura.
 * El contrato viaja en primitivos: al caso de uso del inventario no le llega ningún
 * tipo de dominio de este módulo.
 */
final class InventarioIngresoColeccionAdapter implements IngresoColeccionPort
{
    public function __construct(
        private readonly SolicitudDepositoRepositoryInterface $solicitudRepo,
        private readonly MatrizEspeciesRepositoryInterface $matrizRepo,
        private readonly RecepcionLoteRepositoryInterface $recepcionRepo,
        private readonly IngresarLoteDepositoHandler $ingresar,
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function ingresarLote(string $solicitudId, string $estadoColeccion): ResultadoIngresoColeccion
    {
        $id = SolicitudDepositoId::from($solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($id);
        $matriz = $this->matrizRepo->buscarPorSolicitudId($solicitudId);

        // Sin solicitud o sin matriz no hay nada que ingresar. No es un error: una
        // recepción puede aprobarse antes de que exista matriz en escenarios de prueba.
        if ($solicitud === null || $matriz === null) {
            return new ResultadoIngresoColeccion(0, 0, 0);
        }

        $filas = [];
        foreach (array_values($matriz->registros()) as $posicion => $registro) {
            $filas[] = [
                // Correlativo estable dentro del depósito: es la base del código de
                // catálogo derivado y, con él, de la idempotencia del ingreso.
                'indice' => $posicion + 1,
                'datosDwC' => $registro->datosDwC(),
                'estadoRegistro' => $registro->estado()->value,
                'motivoJustificacion' => $registro->motivoJustificacion(),
            ];
        }

        $lote = $this->recepcionRepo->buscarPorSolicitudId($id);
        $numero = (string) $solicitud->numero();

        $salida = $this->ingresar->handle(new IngresarLoteDepositoInput(
            numeroSolicitud: $numero,
            // El código del lote (QR) es la referencia con la que el curador rastrea la
            // entrega física; si aún no hay recepción se cae al número de solicitud.
            actaRecepcion: $lote !== null ? (string) $lote->codigoQR() : $numero,
            estadoCustodia: $estadoColeccion,
            filas: $filas,
        ));

        return new ResultadoIngresoColeccion(
            especimenesCreados: $salida->especimenesCreados,
            omitidosPorDuplicado: $salida->omitidosPorDuplicado,
            marcadosParaRevision: $salida->marcadosParaRevision,
        );
    }

    public function resumenDeLote(string $solicitudId): ResumenIngresoColeccion
    {
        $solicitud = $this->solicitudRepo->buscarPorId(SolicitudDepositoId::from($solicitudId));
        $matriz = $this->matrizRepo->buscarPorSolicitudId($solicitudId);

        if ($solicitud === null || $matriz === null) {
            return new ResumenIngresoColeccion(0, 0, 0);
        }

        $numero = (string) $solicitud->numero();
        $codigos = [];

        foreach (array_keys(array_values($matriz->registros())) as $posicion) {
            $codigos[] = IngresarLoteDepositoHandler::codigoCatalogoPara($numero, $posicion + 1);
        }

        $resumen = $this->especimenRepo->resumenPorCodigosCatalogo($codigos);

        return new ResumenIngresoColeccion(
            especimenesEnColeccion: $resumen['total'],
            pendientesRevision: $resumen['pendientesRevision'],
            registrosEnMatriz: count($codigos),
        );
    }
}
