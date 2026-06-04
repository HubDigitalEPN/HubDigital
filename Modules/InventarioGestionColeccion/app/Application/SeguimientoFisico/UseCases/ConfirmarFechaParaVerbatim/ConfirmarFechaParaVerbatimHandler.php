<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarFechaParaVerbatim;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Aplica una fecha canónica (yyyy-mm-dd, opcionalmente con fin de rango)
 * a todos los especímenes con un fecha_verbatim dado cuyo fecha_colecta
 * aún está vacío.
 *
 * UPDATE SQL único.
 */
final class ConfirmarFechaParaVerbatimHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ConfirmarFechaParaVerbatimInput $input): ConfirmarFechaParaVerbatimOutput
    {
        $verbatim = trim($input->verbatim);
        if ($verbatim === '') {
            throw new \InvalidArgumentException('El verbatim no puede estar vacío.');
        }

        $fechaInicio = trim($input->fechaInicio);
        if (! $this->esFechaIso($fechaInicio)) {
            throw new \InvalidArgumentException("Fecha de inicio inválida: '{$fechaInicio}' (esperado YYYY-MM-DD).");
        }

        $fechaFin = $input->fechaFin !== null ? trim($input->fechaFin) : null;
        if ($fechaFin !== null && $fechaFin !== '') {
            if (! $this->esFechaIso($fechaFin)) {
                throw new \InvalidArgumentException("Fecha de fin inválida: '{$fechaFin}' (esperado YYYY-MM-DD).");
            }
            if ($fechaFin < $fechaInicio) {
                throw new \InvalidArgumentException('La fecha de fin no puede ser anterior a la de inicio.');
            }
        } else {
            $fechaFin = null;
        }

        $afectados = $this->especimenRepo->enlazarFechaPorVerbatim($verbatim, $fechaInicio, $fechaFin);

        return new ConfirmarFechaParaVerbatimOutput(
            verbatim: $verbatim,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            especimenesAfectados: $afectados,
        );
    }

    private function esFechaIso(string $valor): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return false;
        }
        [$y, $m, $d] = explode('-', $valor);

        return checkdate((int) $m, (int) $d, (int) $y);
    }
}
