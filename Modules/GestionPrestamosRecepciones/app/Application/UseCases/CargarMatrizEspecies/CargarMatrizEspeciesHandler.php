<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class CargarMatrizEspeciesHandler
{
    public function __construct(
        private SolicitudDepositoRepositoryInterface $solicitudRepo,
        private MatrizEspeciesRepositoryInterface $matrizRepo,
        private TransactionManagerPort $transactionManager,
        private EventPublisherPort $eventPublisher,
    ) {}

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

        // TODO: Normalización de datos antes de la ingesta (data cleansing)
        // Antes de persistir cada registro, normalizar los campos que tienen
        // un dominio cerrado conocido para garantizar data limpia desde el origen.
        //
        // IMPORTANTE: los campos concretos aún no están definidos — dependen de
        // qué columnas DwC exija el catálogo de curaduría (InventarioGestionColeccion),
        // que todavía no está implementado. Los ejemplos de abajo son orientativos.
        //
        // Criterio para decidir si un campo es normalizable:
        //   - ¿Existe un catálogo cerrado o estándar externo que lo defina?
        //     → Normalizable (ej. provincias INEC, enums DwC, códigos ISO).
        //   - ¿Es descripción libre del colector?
        //     → No normalizable (ej. locality, habitat, fieldNotes).
        //
        // Estrategia de corrección:
        //   - Diferencia solo de mayúsculas/tildes → corregir silenciosamente.
        //   - Typo con alta similitud → sugerir sin bloquear.
        //   - Sin coincidencia → marcar para revisión, nunca rechazar el envío.
        foreach ($input->registros as $datosRegistro) {
            $nombreCientifico = $datosRegistro['scientificName'] ?? '';
            $matriz->agregarRegistroEspecimen($nombreCientifico);
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
