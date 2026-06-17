<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoAlerta;

/**
 * Manejador del caso de uso para que el curador no acepte una o más justificaciones,
 * dejando la solicitud en estado "Requiere Corrección".
 *
 * {@see RechazarJustificacionesAlertasInput}
 * {@see RechazarJustificacionesAlertasOutput}
 */
final class RechazarJustificacionesAlertasHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
        private NotificacionInvestigadorPort $notificacionInvestigador,
    ) {}

    /**
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     */
    public function __invoke(RechazarJustificacionesAlertasInput $input): RechazarJustificacionesAlertasOutput
    {
        $id = SolicitudDepositoId::from($input->solicitudId);
        $solicitud = $this->repo->buscarPorId($id);

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        // Mapeo de primitivos → VOs de la colección (clave natural: TipoAlerta).
        $tiposRechazados = [];
        foreach ($input->justificacionesRechazadas as $datosJustificacion) {
            $tiposRechazados[] = TipoAlerta::from($datosJustificacion);
        }

        $solicitud->rechazarJustificaciones($input->curadorId, $tiposRechazados);

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $notifRef = $this->notificacionInvestigador->notificarRechazoSolicitud(
            solicitudId: (string) $solicitud->id(),
            investigadorId: $solicitud->investigadorId(),
            comentario: (string) $solicitud->comentarioCurador(),
        );

        return RechazarJustificacionesAlertasOutput::fromPrimitives(
            estado: $solicitud->estado()->value,
            comentarioInvestigador: $solicitud->comentarioCurador(),
            notificacionInvestigadorEnviada: $notifRef !== '',
        );
    }
}
