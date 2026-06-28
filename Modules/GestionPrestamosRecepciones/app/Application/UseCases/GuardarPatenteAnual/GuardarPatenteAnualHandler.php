<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GuardarPatenteAnual;

use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PatenteAnualRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\Patente;

/**
 * Registra o reemplaza la patente del laboratorio para un año.
 *
 * {@see GuardarPatenteAnualInput}
 * {@see GuardarPatenteAnualOutput}
 */
final class GuardarPatenteAnualHandler
{
    public function __construct(
        private readonly PatenteAnualRepositoryInterface $patentes,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(GuardarPatenteAnualInput $input): GuardarPatenteAnualOutput
    {
        $patente = Patente::desde($input->codigo);

        $this->transactionManager->executeTransactional(function () use ($input, $patente): void {
            $this->patentes->guardarParaAnio($input->anio, $patente, $input->curadorId);
        });

        return new GuardarPatenteAnualOutput(
            anio: $input->anio,
            codigo: $patente->valor(),
        );
    }
}
