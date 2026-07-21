<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Traspaso de los especímenes de un lote recibido al módulo que custodia la colección.
 *
 * La implementación es lo único que conoce ambos bounded contexts; desde aquí el
 * ingreso se ve como un servicio externo más, igual que la notificación o la firma.
 */
interface IngresoColeccionPort
{
    /**
     * Ingresa a la colección los especímenes de la matriz de la solicitud dada.
     *
     * Debe ser idempotente: el listener que la invoca corre en cola y puede
     * reintentarse, y un segundo ingreso duplicaría el lote entero.
     *
     * @param  string  $solicitudId  Solicitud cuya recepción física fue aprobada.
     * @param  string  $estadoColeccion  Régimen de tenencia del ingreso (Temporal | Permanente | Cuarentena).
     */
    public function ingresarLote(string $solicitudId, string $estadoColeccion): ResultadoIngresoColeccion;
}
