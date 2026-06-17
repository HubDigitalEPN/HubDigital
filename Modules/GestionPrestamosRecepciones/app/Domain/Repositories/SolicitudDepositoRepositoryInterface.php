<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Puerto de persistencia para el agregado {@see SolicitudDeposito}.
 *
 * Implementado por un repositorio Eloquent en Infrastructure.
 */
interface SolicitudDepositoRepositoryInterface
{
    /** Genera un identificador nuevo para una solicitud aún no persistida. */
    public function nextIdentity(): SolicitudDepositoId;

    /** Genera el siguiente número secuencial de solicitud de depósito. */
    public function nextNumero(): NumeroSolicitudDeposito;

    /** Persiste (inserta o actualiza) la solicitud. */
    public function guardar(SolicitudDeposito $solicitud): void;

    /** Recupera la solicitud por su identificador, o null si no existe. */
    public function buscarPorId(SolicitudDepositoId $id): ?SolicitudDeposito;

    /** Cuenta las solicitudes del investigador y tipo dentro del año en curso (para el límite anual). */
    public function contarPorInvestigadorYTipoEnAnioActual(string $investigadorId, string $tipoTramite): int;

    /** Elimina los borradores abandonados del investigador. */
    public function eliminarBorradoresDe(string $investigadorId): void;
}
