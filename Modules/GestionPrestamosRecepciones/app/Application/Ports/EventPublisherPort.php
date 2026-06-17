<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para publicar eventos de dominio.
 *
 * Lo implementa un adaptador en Infrastructure (síncrono o sobre el bus de eventos de
 * Laravel). Los Handlers lo usan tras persistir un agregado para despachar sus eventos.
 */
interface EventPublisherPort
{
    /** Publica un evento de dominio hacia sus suscriptores. */
    public function publish(object $event): void;
}
