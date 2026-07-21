<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteVerificadaConObservaciones;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteVerificadaFisicamente;

/**
 * Traspasa a la colección los especímenes de un lote cuya recepción física se aprobó.
 *
 * Va en cola a propósito: la matriz puede traer miles de filas y la base está en otra
 * región, así que hacerlo dentro de la petición dejaría al curador esperando delante de
 * una pantalla congelada. El precio es que el ingreso no es inmediato, y por eso el
 * caso de uso de destino es idempotente: si el job se reintenta, no duplica el lote.
 */
final class IngresarLoteEnColeccionListener implements ShouldQueue
{
    /**
     * PENDIENTE — la entrega en cola todavía no funciona.
     *
     * En el proceso de `queue:work` el proveedor del módulo no llega a registrarse:
     * no figura en bootstrap/cache/services.php (nwidart los carga por su propia vía)
     * y la resolución de IngresoColeccionPort revienta con BindingResolutionException.
     * El mismo enlace resuelve sin problema en cualquier otro proceso —tinker, consola,
     * peticiones web—, y este es el primer listener en cola del proyecto, así que el
     * hueco nunca se había ejercitado. Resolver el puerto aquí en vez de por constructor
     * tampoco lo evita: el contenedor del worker sencillamente no tiene el enlace.
     *
     * La cadena de ingesta en sí está verificada contra datos reales ejecutándola de
     * forma síncrona (13 especímenes del depósito MEPN-INV-DEP-00002, idempotente al
     * repetirla). Lo que falta es que el worker vea los enlaces del módulo.
     */
    public function handle(RecepcionLoteVerificadaFisicamente|RecepcionLoteVerificadaConObservaciones $event): void
    {
        $ingresoColeccion = app(IngresoColeccionPort::class);
        $solicitudId = (string) $event->solicitudId;

        $resultado = $ingresoColeccion->ingresarLote(
            solicitudId: $solicitudId,
            estadoColeccion: $event->estadoColeccion->value,
        );

        Log::info('Lote depositado ingresado a la colección', [
            'solicitud_id' => $solicitudId,
            'estado_custodia' => $event->estadoColeccion->value,
            'especimenes_creados' => $resultado->especimenesCreados,
            'omitidos_por_duplicado' => $resultado->omitidosPorDuplicado,
            'marcados_para_revision' => $resultado->marcadosParaRevision,
        ]);
    }
}
