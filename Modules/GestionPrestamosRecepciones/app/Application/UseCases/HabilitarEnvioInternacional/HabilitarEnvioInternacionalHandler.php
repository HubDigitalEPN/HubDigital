<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use RuntimeException;

/**
 * Habilita el envío internacional de un préstamo tras la verificación.
 *
 * {@see HabilitarEnvioInternacionalInput}
 * {@see HabilitarEnvioInternacionalOutput}
 */
final class HabilitarEnvioInternacionalHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly PrestamoRepositoryInterface $prestamoRepo,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * @param HabilitarEnvioInternacionalInput $input
     * @return HabilitarEnvioInternacionalOutput
     * @throws ActaPrestamoNoEncontradaException
     * @throws RuntimeException
     */
    public function handle(HabilitarEnvioInternacionalInput $input): HabilitarEnvioInternacionalOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $acta->adjuntarDocumentoExportacion($input->documentoRuta);

        $prestamo = $this->prestamoRepo->buscarPorActaId($actaId);

        if ($prestamo === null) {
            throw new RuntimeException("Préstamo no encontrado para el acta: {$input->actaId}");
        }

        $prestamo->habilitarEnvio($input->curadorId);

        $this->transactionManager->executeTransactional(function () use ($acta, $prestamo): void {
            $this->actaRepo->guardar($acta);
            $this->prestamoRepo->guardar($prestamo);
            foreach ($acta->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
            foreach ($prestamo->pullEvents() as $event) {
                $this->publisher->publish($event);
            }
        });

        return HabilitarEnvioInternacionalOutput::fromPrestamo($prestamo);
    }
}
