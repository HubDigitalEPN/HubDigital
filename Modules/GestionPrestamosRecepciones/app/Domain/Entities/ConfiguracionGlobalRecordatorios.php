<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Events\ConfiguracionGlobalRecordatoriosActualizada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ConfiguracionGlobalRecordatoriosDefinida;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CadenciaRecordatoriosInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ConfiguracionGlobalRecordatoriosId;

/**
 * Agregado raíz que guarda la configuración global de recordatorios de devolución
 * que define el curador.
 *
 * Almacena la cadencia (lista de días antes del vencimiento en los que se notifica
 * al investigador) y valida que dicha cadencia no esté vacía ni contenga días
 * inválidos. Emite eventos al definirse y actualizarse.
 *
 * Construir vía {@see self::definir()}; rehidratar vía {@see self::reconstituir()}.
 */
final class ConfiguracionGlobalRecordatorios
{
    /** @var list<object> Eventos de dominio acumulados, drenados con {@see pullEvents()}. */
    private array $events = [];

    /**
     * @param  list<int>  $diasAntes
     */
    private function __construct(
        private readonly ConfiguracionGlobalRecordatoriosId $id,
        private string $curadorId,
        private array $diasAntes,
    ) {}

    // ── Named constructors ────────────────────────────────────────────────────

    /**
     * Define la configuración global validando la cadencia.
     *
     * @param  list<int>  $diasAntes  Días antes del vencimiento en que se notifica.
     *
     * @throws CadenciaRecordatoriosInvalidaException Si la lista está vacía o contiene días < 1.
     */
    public static function definir(
        ConfiguracionGlobalRecordatoriosId $id,
        string $curadorId,
        array $diasAntes,
    ): self {
        self::validarCadencia($diasAntes);

        $config = new self(
            id: $id,
            curadorId: $curadorId,
            diasAntes: $diasAntes,
        );

        $config->events[] = new ConfiguracionGlobalRecordatoriosDefinida(
            configuracionId: $id,
            curadorId: $curadorId,
            diasAntes: $diasAntes,
            ocurridoEn: new DateTimeImmutable,
        );

        return $config;
    }

    /**
     * @param  list<int>  $diasAntes
     */
    public static function reconstituir(
        ConfiguracionGlobalRecordatoriosId $id,
        string $curadorId,
        array $diasAntes,
    ): self {
        return new self(
            id: $id,
            curadorId: $curadorId,
            diasAntes: $diasAntes,
        );
    }

    // ── Business methods ──────────────────────────────────────────────────────

    /**
     * Actualiza el curador responsable y la cadencia de recordatorios.
     *
     * @param  list<int>  $diasAntes
     *
     * @throws CadenciaRecordatoriosInvalidaException Si la lista está vacía o contiene días < 1.
     */
    public function actualizar(string $curadorId, array $diasAntes): void
    {
        self::validarCadencia($diasAntes);

        $this->curadorId = $curadorId;
        $this->diasAntes = $diasAntes;

        $this->events[] = new ConfiguracionGlobalRecordatoriosActualizada(
            configuracionId: $this->id,
            curadorId: $curadorId,
            diasAntes: $diasAntes,
            ocurridoEn: new DateTimeImmutable,
        );
    }

    /**
     * @return list<object>
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function id(): ConfiguracionGlobalRecordatoriosId
    {
        return $this->id;
    }

    public function curadorId(): string
    {
        return $this->curadorId;
    }

    /**
     * @return list<int>
     */
    public function diasAntes(): array
    {
        return $this->diasAntes;
    }

    // ── Invariant guard ───────────────────────────────────────────────────────

    /**
     * @param  list<int>  $diasAntes
     */
    private static function validarCadencia(array $diasAntes): void
    {
        if ($diasAntes === []) {
            throw CadenciaRecordatoriosInvalidaException::paraListaVacia();
        }

        $invalidos = array_filter($diasAntes, fn (int $d) => $d < 1);

        if ($invalidos !== []) {
            throw CadenciaRecordatoriosInvalidaException::paraDiasInvalidos($diasAntes);
        }
    }
}
