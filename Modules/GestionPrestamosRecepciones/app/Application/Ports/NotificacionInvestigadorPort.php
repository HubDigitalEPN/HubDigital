<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para notificar al investigador sobre el resultado
 * de la revisión documental de su solicitud.
 *
 * Lo implementa un adaptador en Infrastructure (correo, registro, etc.) que resuelve
 * el destinatario a partir del investigadorId.
 */
interface NotificacionInvestigadorPort
{
    /**
     * Notifica al investigador que el Código QR de su lote ya está disponible para
     * descargar de cara a la entrega física.
     *
     * @return string Referencia/identificador de la notificación generada.
     */
    public function notificarCodigoQrDisponible(string $solicitudId, string $investigadorId, string $codigoQR): string;

    /**
     * Notifica al investigador el rechazo de su solicitud, incluyendo el comentario
     * del curador.
     *
     * @return string Referencia/identificador de la notificación generada.
     */
    public function notificarRechazoSolicitud(string $solicitudId, string $investigadorId, string $comentario): string;
}
