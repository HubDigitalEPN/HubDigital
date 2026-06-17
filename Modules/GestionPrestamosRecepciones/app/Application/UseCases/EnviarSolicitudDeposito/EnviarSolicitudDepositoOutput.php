<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;

/**
 * Output DTO para el caso de uso de enviar una solicitud de depósito a revisión.
 */
final readonly class EnviarSolicitudDepositoOutput
{
    /**
     * @param  EstadoSolicitudDeposito  $estado  Estado actualizado de la solicitud.
     * @param  bool  $notificacionCuradorEnviada  Si se notificó al curador la nueva solicitud por revisar.
     */
    public function __construct(
        public EstadoSolicitudDeposito $estado,
        public bool $notificacionCuradorEnviada = false,
    ) {}

    /**
     * Crea una instancia de salida a partir de valores primitivos.
     */
    public static function fromPrimitives(string $estado, bool $notificacionCuradorEnviada): self
    {
        return new self(
            estado: EstadoSolicitudDeposito::from($estado),
            notificacionCuradorEnviada: $notificacionCuradorEnviada,
        );
    }

    /**
     * Crea una instancia de salida a partir de una entidad SolicitudDeposito.
     *
     * @param  SolicitudDeposito  $solicitud  Entidad de solicitud de depósito.
     */
    public static function fromEntity(SolicitudDeposito $solicitud): self
    {
        return new self(
            estado: $solicitud->estado(),
        );
    }
}
