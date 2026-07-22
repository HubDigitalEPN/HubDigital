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
     * Debe ser idempotente: aprobar dos veces la misma recepción no puede duplicar
     * el lote en la colección.
     *
     * @param  string  $solicitudId  Solicitud cuya recepción física fue aprobada.
     * @param  string  $estadoColeccion  Régimen de tenencia del ingreso (Temporal | Permanente | Cuarentena).
     */
    public function ingresarLote(string $solicitudId, string $estadoColeccion): ResultadoIngresoColeccion;

    /**
     * Qué hay realmente en la colección para este lote.
     *
     * Permite que la pantalla de recepción muestre el resultado del ingreso en vez de
     * limitarse a prometerlo.
     */
    public function resumenDeLote(string $solicitudId): ResumenIngresoColeccion;
}
