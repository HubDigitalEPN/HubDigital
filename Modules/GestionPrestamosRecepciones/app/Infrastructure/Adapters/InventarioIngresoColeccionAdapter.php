<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ResultadoIngresoColeccion;
use Modules\GestionPrestamosRecepciones\Application\Ports\ResultadoVinculacionDeposito;
use Modules\GestionPrestamosRecepciones\Application\Ports\ResumenIngresoColeccion;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarResumenLoteDeposito\ConsultarResumenLoteDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarResumenLoteDeposito\ConsultarResumenLoteDepositoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DevolverLoteDeposito\DevolverLoteDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DevolverLoteDeposito\DevolverLoteDepositoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarHuerfanosDeposito\MarcarHuerfanosDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarHuerfanosDeposito\MarcarHuerfanosDepositoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SanearDatosDwCDeposito\SanearDatosDwCDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SanearDatosDwCDeposito\SanearDatosDwCDepositoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VincularRegistrosDeposito\VincularRegistrosDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VincularRegistrosDeposito\VincularRegistrosDepositoInput;

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
        private readonly DevolverLoteDepositoHandler $devolver,
        private readonly VincularRegistrosDepositoHandler $vincular,
        private readonly ConsultarResumenLoteDepositoHandler $consultarResumen,
        private readonly UsuarioNombrePort $usuarios,
        private readonly SanearDatosDwCDepositoHandler $sanear,
        private readonly MarcarHuerfanosDepositoHandler $marcarHuerfanosHandler,
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
                'registroId' => (string) $registro->id(),
                'datosDwC' => $registro->datosDwC(),
                'estadoRegistro' => $registro->estado()->value,
                'motivoJustificacion' => $registro->motivoJustificacion(),
                // El nombre que vale es el que quedó tras la revisión: si el curador
                // aceptó una corrección tipográfica, la colección tiene que recibir el
                // nombre corregido. Hasta ahora solo viajaba `datosDwC`, así que el
                // depósito ingresaba con el nombre erróneo del depositante aunque en la
                // matriz constara ya corregido.
                'nombreCientificoCanonico' => $registro->nombreCorregido() ?? $registro->nombreCientifico(),
            ];
        }

        $lote = $this->recepcionRepo->buscarPorSolicitudId($id);
        $numero = (string) $solicitud->numero();
        $depositante = $this->usuarios->obtenerDatosDepositante($solicitud->investigadorId());

        $salida = $this->ingresar->handle(new IngresarLoteDepositoInput(
            numeroSolicitud: $numero,
            // Solo el código QR del lote, que es la referencia con la que el curador
            // rastrea la entrega física. Sin recepción va null: antes se caía al número
            // de solicitud y la columna acababa significando dos cosas distintas según la
            // fila, sin nada que dijera cuál.
            codigoQrLote: $lote !== null ? (string) $lote->codigoQR() : null,
            estadoCustodia: $estadoColeccion,
            filas: $filas,
            // Identidad del trámite: sobrevive a las renumeraciones del número de
            // solicitud, que ya han ocurrido dos veces sin propagarse a la colección.
            solicitudDepositoId: $solicitudId,
            tipoTramite: $solicitud->tipoTramite(),
            recibidoEn: new \DateTimeImmutable,
            // Quién depositó. La colección lo convierte en su propia entidad depositante;
            // sin esto el material entraba sin dueño y había que volver aquí a
            // preguntarlo.
            depositanteNombre: $depositante?->nombre,
            depositanteInstitucion: $depositante?->institucion,
            depositanteEmail: $depositante?->email,
            // Única fuente de los permisos: la matriz dejó de pedirlos por fila.
            permisoRecoleccion: $solicitud->nroPermisoRecoleccion(),
            permisoMovilizacion: $solicitud->nroPermisoMovilizacion(),
            // Quién dio entrada al material. Se lee del curador responsable de la
            // solicitud en vez de arrastrarlo por el evento: el dato ya está aquí.
            registradoPor: $this->nombreCurador($solicitud->curadorResponsable()),
        ));

        // Se cierra el vínculo aquí mismo: cada fila de la matriz queda apuntando al
        // espécimen que produjo. Antes había que correr después un comando aparte, y
        // mientras tanto la relación solo se podía deducir recalculando códigos.
        $filasAnotadas = $this->matrizRepo->vincularEspecimenes($salida->especimenPorRegistro());

        $motivos = [];
        foreach ($salida->resultados as $resultado) {
            if ($resultado->registroId !== null && $resultado->motivoRevision !== null) {
                $motivos[$resultado->registroId] = $resultado->motivoRevision;
            }
        }

        return new ResultadoIngresoColeccion(
            especimenesCreados: $salida->especimenesCreados,
            omitidosPorDuplicado: $salida->omitidosPorDuplicado,
            marcadosParaRevision: $salida->marcadosParaRevision,
            filasAnotadas: $filasAnotadas,
            motivosRevision: $motivos,
        );
    }

    /** Nombre legible del curador, o su identificador si no se puede resolver. */
    private function nombreCurador(?string $curadorId): ?string
    {
        if ($curadorId === null || trim($curadorId) === '') {
            return null;
        }

        return $this->usuarios->obtenerNombre($curadorId) ?? $curadorId;
    }

    public function loteYaIngresado(string $solicitudId): bool
    {
        $solicitud = $this->solicitudRepo->buscarPorId(SolicitudDepositoId::from($solicitudId));

        if ($solicitud === null) {
            return false;
        }

        return $this->consultarResumen->handle(new ConsultarResumenLoteDepositoInput($solicitudId))->hayMaterialIngresado();
    }

    public function vincularRegistros(string $solicitudId, bool $simular = true): ResultadoVinculacionDeposito
    {
        $matriz = $this->matrizRepo->buscarPorSolicitudId($solicitudId);

        if ($matriz === null) {
            return ResultadoVinculacionDeposito::sinMatriz();
        }

        // Mismo recorrido que usó el ingreso: la posición dentro de este array es lo que
        // se convirtió en el índice del código de catálogo.
        $registroIdPorIndice = [];
        $nombrePorIndice = [];

        foreach (array_values($matriz->registros()) as $posicion => $registro) {
            $indice = $posicion + 1;
            $registroIdPorIndice[$indice] = (string) $registro->id();

            // Referencia para verificar el ORDEN, no la corrección del nombre: hay que
            // comparar contra lo que el ingreso escribió de verdad, y el ingreso tomaba
            // el nombre del registro DwC, no la corrección que el curador hubiera
            // aceptado. Usar `nombreCorregido()` aquí haría fallar como "discrepancia de
            // orden" lo que en realidad es la pérdida de la corrección — un problema
            // distinto, que se arregla en el ingreso y se sanea aparte.
            $datosDwC = $registro->datosDwC();
            $nombrePorIndice[$indice] = is_string($datosDwC['scientificName'] ?? null)
                && trim($datosDwC['scientificName']) !== ''
                    ? $datosDwC['scientificName']
                    : $registro->nombreCientifico();
        }

        $salida = $this->vincular->handle(new VincularRegistrosDepositoInput(
            solicitudDepositoId: $solicitudId,
            registroIdPorIndice: $registroIdPorIndice,
            nombreCientificoPorIndice: $nombrePorIndice,
            simular: $simular,
        ));

        // El lado de este módulo solo se escribe si el inventario dio el orden por bueno.
        $filasAnotadas = 0;

        if (! $simular && $salida->esConsistente()) {
            $filasAnotadas = $this->matrizRepo->vincularEspecimenes($salida->especimenPorRegistro);
        }

        return new ResultadoVinculacionDeposito(
            especimenesVinculados: $salida->vinculados,
            filasAnotadas: $filasAnotadas,
            yaVinculados: $salida->yaVinculados,
            sinEspecimen: $salida->sinEspecimen,
            discrepancias: $salida->discrepancias,
            simulado: $salida->simulado,
        );
    }

    public function resumenDeLote(string $solicitudId): ResumenIngresoColeccion
    {
        $matriz = $this->matrizRepo->buscarPorSolicitudId($solicitudId);
        $resumen = $this->consultarResumen->handle(new ConsultarResumenLoteDepositoInput($solicitudId));

        return new ResumenIngresoColeccion(
            especimenesEnColeccion: $resumen->especimenesEnColeccion,
            pendientesRevision: $resumen->pendientesRevision,
            registrosEnMatriz: $matriz === null ? 0 : count($matriz->registros()),
        );
    }

    public function sanearDatosDwC(string $solicitudId, bool $simular = true): array
    {
        $matriz = $this->matrizRepo->buscarPorSolicitudId($solicitudId);

        if ($matriz === null) {
            return ['especimenesTocados' => 0, 'columnasEscritas' => 0];
        }

        $filasPorIndice = [];

        foreach (array_values($matriz->registros()) as $posicion => $registro) {
            $filasPorIndice[$posicion + 1] = $registro->datosDwC();
        }

        $solicitud = $this->solicitudRepo->buscarPorId(SolicitudDepositoId::from($solicitudId));
        $depositante = $solicitud === null
            ? null
            : $this->usuarios->obtenerDatosDepositante($solicitud->investigadorId());

        $salida = $this->sanear->handle(new SanearDatosDwCDepositoInput(
            solicitudDepositoId: $solicitudId,
            filasPorIndice: $filasPorIndice,
            simular: $simular,
            depositanteNombre: $depositante?->nombre,
            depositanteInstitucion: $depositante?->institucion,
            depositanteEmail: $depositante?->email,
        ));

        return [
            'especimenesTocados' => $salida->especimenesTocados,
            'columnasEscritas' => $salida->columnasEscritas,
        ];
    }

    public function marcarHuerfanos(string $motivo): int
    {
        return $this->marcarHuerfanosHandler->handle(new MarcarHuerfanosDepositoInput($motivo))->marcados;
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

        foreach (array_keys(array_values($matriz->registros())) as $posicion) {
            $codigos[] = IngresarLoteDepositoHandler::codigoCatalogoPara($numero, $posicion + 1);
        }

        return $codigos;
    }
}
