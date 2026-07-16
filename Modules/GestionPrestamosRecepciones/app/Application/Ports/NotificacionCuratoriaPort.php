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

    /**
     * Notifica a los demás curadores que un colega tomó una decisión documental
     * (aprobó o rechazó) sobre una solicitud. Incluye el motivo si fue rechazo.
     *
     * @param  string  $curadorQueDecideId  Curador que tomó la decisión (se excluye del envío).
     * @param  'aprobada'|'rechazada'  $decision
     * @param  string|null  $motivo  Motivo del rechazo (solo aplica cuando $decision es 'rechazada').
     * @return string Referencia/identificador de la notificación generada.
     */
    public function notificarDecisionDocumentalAOtrosCuradores(
        string $solicitudId,
        string $curadorQueDecideId,
        string $decision,
        ?string $motivo = null,
    ): string;
}
