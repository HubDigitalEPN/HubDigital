<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

final class DevolverActaParaRefirmarHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(DevolverActaParaRefirmarInput $input): DevolverActaParaRefirmarOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $acta->devolver(
            investigadorId: $input->investigadorId,
            motivo: $input->motivo,
        );

        $this->transactionManager->executeTransactional(function () use ($acta): void {
            $this->actaRepo->guardar($acta);
            foreach ($acta->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return DevolverActaParaRefirmarOutput::fromPrimitives($acta);
    }
}
