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

    /**
     * Notifica al investigador la finalización exitosa de la entrega física del lote,
     * cuyos especímenes ingresaron a la colección en el estado indicado.
     *
     * @return string Referencia/identificador de la notificación generada.
     */
    public function notificarRecepcionFinalizada(string $solicitudId, string $investigadorId, string $estadoColeccion): string;

    /**
     * Notifica al investigador la finalización de la entrega con observaciones
     * registradas en el Acta Digital de Recepción.
     *
     * @param  list<string>  $observaciones
     * @return string Referencia/identificador de la notificación generada.
     */
    public function notificarRecepcionConObservaciones(string $solicitudId, string $investigadorId, array $observaciones): string;

    /**
     * Notifica al investigador que el Acta de Recepción, firmada electrónicamente por
     * el curador, ya está disponible para descargar.
     *
     * @return string Referencia/identificador de la notificación generada.
     */
    public function notificarActaRecepcionDisponible(string $solicitudId, string $investigadorId): string;

    /**
     * Avisa de que curaduría ajustó un dato de formato de la matriz.
     *
     * El depositante firmó la matriz al enviarla, así que un cambio posterior —aunque
     * sea menor y le evite reenviarla— no puede quedar en silencio.
     */
    public function notificarCorreccionCuratorial(
        string $solicitudId,
        string $investigadorId,
        string $campo,
        ?string $valorAnterior,
        ?string $valorNuevo,
    ): string;

    /**
     * Notifica al investigador la orden de acción correctiva emitida al suspenderse la
     * recepción del lote por una anomalía subsanable.
     *
     * @return string Referencia/identificador de la notificación generada.
     */
    public function notificarOrdenAccionCorrectiva(string $solicitudId, string $investigadorId, string $motivoFallo, string $accionCorrectiva): string;
}
