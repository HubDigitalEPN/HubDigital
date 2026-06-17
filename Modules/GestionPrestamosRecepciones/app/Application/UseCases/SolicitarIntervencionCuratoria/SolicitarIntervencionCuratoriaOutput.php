<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria;

/**
 * Datos de salida tras solicitar intervención curatoria.
 */
final readonly class SolicitarIntervencionCuratoriaOutput
{
    public function __construct(
        public bool $cargaDocumentalPausada,
        public bool $notificacionCuradorEnviada,
        public string $curadorNotificadoId,
    ) {}
}
