<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteVerificadaConObservaciones;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteVerificadaFisicamente;

/**
 * Traspasa a la colección los especímenes de un lote cuya recepción física se aprobó.
 *
 * Se ejecuta de forma síncrona, dentro de la misma petición que aprueba la recepción.
 *
 * La idea inicial fue encolarlo, suponiendo que una matriz grande congelaría la
 * pantalla del curador. Al medirlo resultó falso: de los 3.002 ms que tardó el ingreso
 * de 13 especímenes, 2.820 ms eran coste fijo de red contra la base (que vive en otra
 * región) y las inserciones van agrupadas de 500 en 500. Es decir, el coste apenas
 * depende del número de filas, así que el motivo para encolarlo no se sostenía.
 *
 * Además la entrega en cola no llegaba a funcionar: en el proceso de `queue:work` el
 * proveedor del módulo no se registra —no figura en bootstrap/cache/services.php,
 * nwidart los carga por su propia vía— y resolver IngresoColeccionPort revienta con
 * BindingResolutionException, aunque el mismo enlace resuelva en tinker, consola y web.
 * Si algún día una matriz justifica volver a la cola, hay que resolver eso primero.
 *
 * El caso de uso de destino es idempotente igualmente: aprobar dos veces no duplica.
 */
final class IngresarLoteEnColeccionListener
{
    public function __construct(
        private readonly IngresoColeccionPort $ingresoColeccion,
    ) {}

    public function handle(RecepcionLoteVerificadaFisicamente|RecepcionLoteVerificadaConObservaciones $event): void
    {
        $solicitudId = (string) $event->solicitudId;

        $resultado = $this->ingresoColeccion->ingresarLote(
            solicitudId: $solicitudId,
            estadoColeccion: $event->estadoColeccion->value,
        );

        Log::info('Lote depositado ingresado a la colección', [
            'solicitud_id' => $solicitudId,
            'estado_custodia' => $event->estadoColeccion->value,
            'especimenes_creados' => $resultado->especimenesCreados,
            'omitidos_por_duplicado' => $resultado->omitidosPorDuplicado,
            'marcados_para_revision' => $resultado->marcadosParaRevision,
            'filas_anotadas' => $resultado->filasAnotadas,
        ]);
    }
}
