<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionDeposito;

use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Cierra el ciclo de un depósito temporal: el curador registra que devolvió el material
 * y los especímenes salen de la colección.
 *
 * Hasta ahora el trámite terminaba en "Aprobada Documentalmente" y el material se
 * quedaba indefinidamente en el catálogo aunque el depositante ya se lo hubiera
 * llevado, de modo que la colección afirmaba tener cosas que no tenía.
 *
 * Los especímenes no se borran: quedan marcados como devueltos y con su fecha de
 * salida, porque el rastro de qué estuvo bajo custodia es documentación de la colección.
 */
final class RegistrarDevolucionDepositoHandler
{
    public function __construct(
        private readonly SolicitudDepositoRepositoryInterface $repo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
        private readonly IngresoColeccionPort $ingresoColeccion,
    ) {}

    /**
     * @throws SolicitudNoEncontradaException Si la solicitud no existe.
     * @throws \DomainException Si el trámite es una donación o el estado no lo permite.
     */
    public function __invoke(RegistrarDevolucionDepositoInput $input): RegistrarDevolucionDepositoOutput
    {
        $solicitud = $this->repo->buscarPorId(SolicitudDepositoId::from($input->solicitudId));

        if ($solicitud === null) {
            throw SolicitudNoEncontradaException::conId($input->solicitudId);
        }

        $solicitud->registrarDevolucion($input->curadorId);
        $devueltoEn = new \DateTimeImmutable;

        $this->transactionManager->executeTransactional(function () use ($solicitud): void {
            $this->repo->guardar($solicitud);
            foreach ($solicitud->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        // El material sale de la colección después de que el trámite quede cerrado: si
        // el dominio rechaza la devolución, no se toca nada del catálogo.
        $devueltos = $this->ingresoColeccion->devolverLote($input->solicitudId, $devueltoEn);

        return new RegistrarDevolucionDepositoOutput(
            estado: $solicitud->estado()->value,
            especimenesDevueltos: $devueltos,
        );
    }
}
