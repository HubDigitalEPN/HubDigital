<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para notificar a la curaduría que una solicitud
 * requiere intervención.
 *
 * Lo implementa un adaptador en Infrastructure (correo, registro, etc.).
 */
interface NotificacionCuratoriaPort
{
    /**
     * Notifica a la curaduría que la solicitud requiere intervención.
     *
     * @return string Referencia/identificador de la notificación generada.
     */
    public function notificarIntervencionRequerida(string $solicitudId, string $investigadorId): string;

    /**
     * Notifica a la curaduría que hay una nueva solicitud por revisar.
     *
     * @return string Referencia/identificador del curador notificado.
     */
    public function notificarNuevaSolicitudPorRevisar(string $solicitudId): string;
}
