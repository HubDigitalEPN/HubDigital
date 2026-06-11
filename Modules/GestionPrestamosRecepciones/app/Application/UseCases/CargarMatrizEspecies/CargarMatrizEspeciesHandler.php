<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\NormalizadorCamposDwC;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Caso de uso: Carga y valida la matriz de especies (DwC) asociada a una solicitud.
 *
 * Procesa los registros de especímenes, aplica normalizaciones de campos y valida
 * que la estructura DwC cumpla con los requisitos mínimos según el tipo de trámite.
 */
final class CargarMatrizEspeciesHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $solicitudRepo,
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
        private NormalizadorCamposDwC $normalizador,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param  CargarMatrizEspeciesInput  $input
     * @return CargarMatrizEspeciesOutput
     *
     * @throws \DomainException Si la solicitud no existe.
     * @throws \Modules\GestionPrestamosRecepciones\Domain\Exceptions\CamposDwCFaltantesException Si faltan campos críticos.
     */
    public function __invoke(CargarMatrizEspeciesInput $input): CargarMatrizEspeciesOutput
    {
        $solicitud = $this->solicitudRepo->buscarPorId(SolicitudDepositoId::from($input->solicitudId));

        if ($solicitud === null) {
            throw new \DomainException(
                sprintf('No se encontró la solicitud con ID "%s"', $input->solicitudId)
            );
        }

        $existente = $this->matrizRepo->buscarPorSolicitudId($input->solicitudId);
        $matrizId = $existente?->id() ?? $this->matrizRepo->nextIdentity();

        $matriz = MatrizEspecies::crear(
            id: $matrizId,
            solicitudId: $input->solicitudId,
            camposDwCPresentes: $input->camposDwCPresentes,
            tipoTramite: $solicitud->tipoTramite(),
        );

        $matriz->validarCamposDwC($input->camposCriticos, $input->camposRecomendados);

        foreach ($input->registros as $datosRegistro) {
            $resultado = $this->normalizador->normalizar($datosRegistro);
            $nombreCientifico = $resultado->registro['scientificName'] ?? '';
            $matriz->agregarRegistroEspecimen(
                $nombreCientifico,
                datosDwC: $resultado->registro,
                normalizaciones: $resultado->cambios,
            );
        }

        $validacionTipograficaAplicada = $solicitud->tipoTramite() !== 'Donación';

        $this->transactionManager->executeTransactional(function () use ($matriz): void {
            $this->matrizRepo->guardar($matriz);

            foreach ($matriz->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        return new CargarMatrizEspeciesOutput(
            matrizId: (string) $matriz->id(),
            estadoMatriz: $matriz->estado(),
            validacionTipograficaAplicada: $validacionTipograficaAplicada,
            totalRegistros: count($input->registros),
            camposRecomendadosFaltantes: $matriz->camposRecomendadosFaltantes(),
        );
    }
}
