<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito;

use App\Support\CatalogoLocalidadesDeposito;
use App\Support\CatalogoLocalidadesEcuador;
use App\Support\CatalogoTerritorialEcuador;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\ReglaDocumentacionRequerida;
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
            if (! in_array($solicitud->estado()->value, ['En Borrador', 'Requiere Corrección'], true)) {
                throw new \DomainException('El origen solo puede cambiarse durante la preparación o corrección de la solicitud.');
            }

            $nombreLocalidad = trim($input->localidad ?? '') ?: null;
            if ($input->finalidadActividad !== null) {
                $solicitud->declararFinalidadActividad($input->finalidadActividad);
            }
            (new ReglaDocumentacionRequerida)->determinar(
                $solicitud->tipoTramite(), $input->origenRecoleccion, $input->situacionRegulatoria, $input->provinciaOrigen, $solicitud->finalidadActividad(),
            );
            if ($solicitud->finalidadActividad() !== null && $input->origenRecoleccion === 'Nacional (Ecuador)' && ! filled($input->localidadOrigenCodigo)) {
                throw new \DomainException('Selecciona una localidad activa del catálogo INEC. Si falta, solicita su alta al administrador.');
            }
            if ($input->origenRecoleccion === 'Nacional (Ecuador)'
                && ! in_array($input->provinciaOrigen, array_column(CatalogoTerritorialEcuador::provincias(), 'nombre'), true)) {
                throw new \DomainException('Selecciona una provincia del catálogo de Ecuador.');
            }
            $localidad = null;
            if ($input->origenRecoleccion === 'Nacional (Ecuador)' && $input->localidadOrigenCodigo !== null) {
                $localidad = CatalogoLocalidadesEcuador::buscar(
                    $input->provinciaOrigen ?? '', $input->localidadOrigenCodigo ?? '',
                );
                if ($localidad === null) {
                    throw new \DomainException('Selecciona una localidad activa de la provincia indicada.');
                }
                $nombreLocalidad = $localidad->nombre;
            } elseif ($nombreLocalidad !== null
                && ! ($solicitud->localidad() === $nombreLocalidad && $solicitud->provinciaOrigen() === $input->provinciaOrigen)
                && ! CatalogoLocalidadesDeposito::contiene($input->provinciaOrigen ?? '', $nombreLocalidad)) {
                throw new \DomainException('Selecciona una localidad del catálogo público de la provincia indicada.');
            }
            $cambio = $solicitud->provinciaOrigen() !== $input->provinciaOrigen
                || ($input->finalidadActividad !== null && SolicitudDepositoEloquentModel::findOrFail($input->solicitudId)->finalidad_actividad !== $input->finalidadActividad)
                || $solicitud->localidadOrigenCodigo() !== $input->localidadOrigenCodigo
                || $solicitud->localidad() !== $nombreLocalidad
                || $solicitud->situacionRegulatoria() !== $input->situacionRegulatoria;
            $solicitud->declararOrigenRecoleccion($input->origenRecoleccion);
            $solicitud->declararSituacionRegulatoria($input->situacionRegulatoria);
            if (! empty($input->provinciaOrigen)) {
                $solicitud->declararProvincia($input->provinciaOrigen);
            }
            $solicitud->declararLocalidadOrigen($input->localidadOrigenCodigo, $nombreLocalidad);
            $this->repo->guardar($solicitud);

            $modelo = SolicitudDepositoEloquentModel::query()->findOrFail($input->solicitudId);
            $cambios = [];
            if ($cambio) {
                $metadata = $modelo->extraccion_metadatos ?? [];
                unset($metadata['ejecucion_id'], $metadata['confirmacion_humana']);
                $cambios += ['extraccion_metadatos' => $metadata,
                    'extraccion_estado' => 'pendiente', 'documentos_procesados' => []];
            }
            if ($cambios !== []) {
                $modelo->forceFill($cambios)->save();
            }
            $eventos = $solicitud->pullEvents();
            $output = ActualizarOrigenSolicitudDepositoOutput::fromEntity($solicitud);
        });
        foreach ($eventos as $event) {
            $this->eventPublisher->publish($event);
        }

        return $output;
    }
}
