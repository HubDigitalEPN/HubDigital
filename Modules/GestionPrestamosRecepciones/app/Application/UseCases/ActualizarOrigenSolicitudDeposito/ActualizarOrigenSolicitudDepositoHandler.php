<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito;

use App\Support\CatalogoLocalidadesEcuador;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/** Guarda la provincia y localidad declaradas, conservando la comprobación de cada PDF. */
final class ActualizarOrigenSolicitudDepositoHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $repo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

    public function __invoke(ActualizarOrigenSolicitudDepositoInput $input): ActualizarOrigenSolicitudDepositoOutput
    {
        $output = null;
        $eventos = [];
        $this->transactionManager->executeTransactional(function () use ($input, &$output, &$eventos): void {
            $id = SolicitudDepositoId::from($input->solicitudId);
            $solicitud = $this->repo->buscarPorIdParaActualizar($id);
            if ($solicitud === null) {
                throw SolicitudNoEncontradaException::conId($input->solicitudId);
            }

            $localidad = null;
            if ($input->origenRecoleccion === 'Nacional (Ecuador)') {
                $localidad = CatalogoLocalidadesEcuador::buscar(
                    $input->provinciaOrigen ?? '', $input->localidadOrigenCodigo ?? '',
                );
                if ($localidad === null) {
                    throw new \DomainException('Selecciona una localidad activa de la provincia indicada.');
                }
            }
            $cambio = $solicitud->provinciaOrigen() !== $input->provinciaOrigen
                || $solicitud->localidadOrigenCodigo() !== $input->localidadOrigenCodigo
                || $solicitud->situacionRegulatoria() !== $input->situacionRegulatoria;
            $solicitud->declararOrigenRecoleccion($input->origenRecoleccion);
            $solicitud->declararSituacionRegulatoria($input->situacionRegulatoria);
            if (! empty($input->provinciaOrigen)) {
                $solicitud->declararProvincia($input->provinciaOrigen);
            }
            $solicitud->declararLocalidadOrigen($input->localidadOrigenCodigo, $localidad?->nombre);
            $this->repo->guardar($solicitud);

            $modelo = SolicitudDepositoEloquentModel::query()->findOrFail($input->solicitudId);
            $cambios = [];
            if ($cambio) {
                $metadata = $modelo->extraccion_metadatos ?? [];
                unset($metadata['ejecucion_id'], $metadata['confirmacion_humana']);
                $cambios += ['extraccion_metadatos' => $metadata,
                    'extraccion_estado' => 'pendiente', 'documentos_procesados' => []];
            }
            if ($cambios !== []) $modelo->forceFill($cambios)->save();
            $eventos = $solicitud->pullEvents();
            $output = ActualizarOrigenSolicitudDepositoOutput::fromEntity($solicitud);
        });
        foreach ($eventos as $event) {
            $this->eventPublisher->publish($event);
        }

        return $output;
    }
}
