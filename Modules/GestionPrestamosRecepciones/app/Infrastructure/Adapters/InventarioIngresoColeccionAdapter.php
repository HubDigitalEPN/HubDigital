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
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DevolverLoteDeposito\DevolverLoteDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DevolverLoteDeposito\DevolverLoteDepositoInput;
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
        private readonly DevolverLoteDepositoHandler $devolver,
    ) {}

    public function ingresarLote(string $solicitudId, string $estadoColeccion): ResultadoIngresoColeccion
    {
        $id = SolicitudDepositoId::from($solicitudId);
        $solicitud = $this->solicitudRepo->buscarPorId($id);
        $matriz = $this->matrizRepo->buscarPorSolicitudId($solicitudId);

        if ($solicitud === null) {
            throw new \DomainException('No es posible ingresar el lote: no existe la solicitud de depósito asociada.');
        }

        if ($matriz === null) {
            throw new \DomainException('No es posible ingresar el lote: el expediente no tiene una matriz de especímenes.');
        }

        if ($matriz->registros() === []) {
            throw new \DomainException('No es posible ingresar el lote: la matriz de especímenes no contiene filas.');
        }

        $filas = [];
        foreach ($matriz->registros() as $registroId => $registro) {
            $filas[] = [
                // El UUID de la fila no cambia al ordenar la matriz. Los códigos de
                // catálogo nuevos se derivan de él para que una corrección editorial
                // no altere la identidad de especímenes ya ingresados.
                'identificadorEstable' => $registroId,
                'datosDwC' => $registro->datosDwC(),
                'estadoRegistro' => $registro->estado()->value,
                'motivoJustificacion' => $registro->motivoJustificacion(),
            ];
        }
        $filas = $this->filasParaColeccion($solicitudId, (string)$solicitud->numero(), $filas);

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
        $codigos = $this->codigosDelLote($solicitudId);

        if ($codigos === []) {
            return new ResumenIngresoColeccion(0, 0, 0);
        }

        $resumen = $this->especimenRepo->resumenPorCodigosCatalogo($codigos);

        return new ResumenIngresoColeccion(
            especimenesEnColeccion: $resumen['total'],
            pendientesRevision: $resumen['pendientesRevision'],
            registrosEnMatriz: count($codigos),
        );
    }

    public function devolverLote(string $solicitudId, \DateTimeImmutable $devueltoEn): int
    {
        $codigos = $this->codigosDelLote($solicitudId);

        if ($codigos === []) {
            return 0;
        }

        return $this->devolver->handle(new DevolverLoteDepositoInput(
            codigosCatalogo: $codigos,
            devueltoEn: $devueltoEn,
        ))->especimenesDevueltos;
    }

    /**
     * Códigos de catálogo que corresponden a este depósito.
     *
     * Se derivan del número de solicitud y la posición en la matriz, igual que al
     * ingresar: es el mismo cálculo determinista y por eso vive en un solo sitio.
     *
     * @return string[]
     */
    private function codigosDelLote(string $solicitudId): array
    {
        $solicitud = $this->solicitudRepo->buscarPorId(SolicitudDepositoId::from($solicitudId));
        $matriz = $this->matrizRepo->buscarPorSolicitudId($solicitudId);

        if ($solicitud === null || $matriz === null) {
            return [];
        }

        $numero = (string) $solicitud->numero();
        $codigos = [];

        $declaradas = [];
        foreach ($matriz->registros() as $registroId => $registro) {
            $declaradas[] = ['identificadorEstable' => $registroId, 'datosDwC' => $registro->datosDwC(), 'estadoRegistro' => $registro->estado()->value, 'motivoJustificacion' => $registro->motivoJustificacion()];
        }
        $filas = $this->filasParaColeccion($solicitudId, $numero, $declaradas);
        foreach ($filas as $fila) {
            $codigos[] = IngresarLoteDepositoHandler::codigoCatalogoPara($numero, $fila['identificadorEstable']);
        }

        return $codigos;
    }

    private function filasParaColeccion(string $solicitudId, string $numero, array $declaradas): array
    {
        // Un acta histórica ya ingresada conserva sus códigos y su trazabilidad.
        // La corrección no vuelve a interpretar ni duplica esos lotes cerrados.
        $codigosHistoricos = array_map(static fn ($fila): string => IngresarLoteDepositoHandler::codigoCatalogoPara($numero, $fila['identificadorEstable']), $declaradas);
        if ($this->especimenRepo->resumenPorCodigosCatalogo($codigosHistoricos)['total'] > 0) return $declaradas;
        return app(\Modules\GestionPrestamosRecepciones\Infrastructure\Services\ContenidoRecibidoDeposito::class)->paraIngreso($solicitudId, $declaradas);
    }
}
