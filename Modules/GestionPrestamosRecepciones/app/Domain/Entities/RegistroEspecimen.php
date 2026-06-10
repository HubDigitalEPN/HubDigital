<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RegistroEspecimenId;

/**
 * Entidad que representa un registro individual de espécimen dentro de una
 * {@see MatrizEspecies}.
 *
 * Conserva el nombre científico declarado, una posible corrección taxonómica, su
 * registro Darwin Core completo (normalizado) y su estado de validación
 * ({@see EstadoRegistroEspecimen}). Soporta aceptar/revertir correcciones
 * tipográficas y justificar hallazgos no catalogados. Los cambios de estado se
 * validan en cada método de negocio.
 */
final class RegistroEspecimen
{
    private RegistroEspecimenId $id;

    private string $nombreCientifico;

    private ?string $nombreCorregido;

    private EstadoRegistroEspecimen $estado;

    private bool $noCatalogado;

    private ?string $motivoJustificacion;

    /** @var array<string, mixed> Registro DwC completo normalizado desde el Excel */
    private array $datosDwC = [];

    /** @var list<array{campo: string, original: mixed, normalizado: mixed}> Campos que fueron normalizados */
    private array $normalizaciones = [];

    private function __construct() {}

    /**
     * @param  array<string, mixed>  $datosDwC  Registro DwC completo normalizado
     * @param  list<array{campo: string, original: mixed, normalizado: mixed}>  $normalizaciones
     */
    public static function crear(
        RegistroEspecimenId $id,
        string $nombreCientifico,
        bool $noCatalogado = false,
        EstadoRegistroEspecimen $estadoInicial = EstadoRegistroEspecimen::Pendiente,
        array $datosDwC = [],
        array $normalizaciones = [],
    ): self {
        if (trim($nombreCientifico) === '') {
            throw new \DomainException('El nombre científico del espécimen no puede estar vacío');
        }

        $registro = new self;
        $registro->id = $id;
        $registro->nombreCientifico = $nombreCientifico;
        $registro->nombreCorregido = null;
        $registro->estado = $estadoInicial;
        $registro->noCatalogado = $noCatalogado;
        $registro->motivoJustificacion = null;
        $registro->datosDwC = $datosDwC;
        $registro->normalizaciones = $normalizaciones;

        return $registro;
    }

    /**
     * Acepta una corrección tipográfica del nombre científico, marcándolo como
     * catalogado y validado. Solo permitido en estado Pendiente.
     *
     * @throws \DomainException Si la especie corregida está vacía o el estado no es Pendiente.
     */
    public function aceptarCorreccion(string $especieCorregida): void
    {
        if (trim($especieCorregida) === '') {
            throw new \DomainException('La especie corregida no puede estar vacía');
        }

        if (! $this->estado->equals(EstadoRegistroEspecimen::Pendiente)) {
            throw new \DomainException(
                sprintf(
                    'Solo se puede aceptar una corrección en estado "Pendiente", estado actual: "%s"',
                    $this->estado->value
                )
            );
        }

        $this->nombreCorregido = $especieCorregida;
        $this->noCatalogado = false;
        $this->estado = EstadoRegistroEspecimen::ValidadoTecnicamente;
    }

    /**
     * Justifica un hallazgo no catalogado, transicionándolo a validación manual por
     * curaduría. Solo permitido en registros marcados como no catalogados.
     *
     * @throws \DomainException Si el motivo está vacío o el registro no es no catalogado.
     */
    public function justificar(string $motivo): void
    {
        if (trim($motivo) === '') {
            throw new \DomainException('El motivo de justificación no puede estar vacío');
        }

        if (! $this->noCatalogado) {
            throw new \DomainException(
                'Solo se pueden justificar registros marcados como no catalogados'
            );
        }

        $this->motivoJustificacion = $motivo;
        $this->estado = EstadoRegistroEspecimen::ValidacionManualPorCuraduria;
    }

    public function marcarComoNoCatalogado(): void
    {
        $this->noCatalogado = true;
    }

    /**
     * Revierte una corrección previamente aceptada, devolviendo el registro a Pendiente.
     *
     * @throws \DomainException Si el registro no había sido corregido por sugerencia.
     */
    public function revertirCorreccion(): void
    {
        if (! $this->estado->equals(EstadoRegistroEspecimen::ValidadoTecnicamente) || $this->nombreCorregido === null) {
            throw new \DomainException(
                'Solo se puede revertir una corrección en registros que fueron corregidos por sugerencia'
            );
        }

        $this->nombreCorregido = null;
        $this->estado = EstadoRegistroEspecimen::Pendiente;
    }

    /**
     * Cambia el motivo de justificación de un registro ya justificado.
     *
     * @throws \DomainException Si el motivo está vacío o el estado no es ValidacionManualPorCuraduria.
     */
    public function cambiarJustificacion(string $nuevoMotivo): void
    {
        if (trim($nuevoMotivo) === '') {
            throw new \DomainException('El motivo de justificación no puede estar vacío');
        }

        if (! $this->estado->equals(EstadoRegistroEspecimen::ValidacionManualPorCuraduria)) {
            throw new \DomainException(
                'Solo se puede cambiar la justificación de registros en estado "Validación Manual por Curaduría"'
            );
        }

        $this->motivoJustificacion = $nuevoMotivo;
    }

    // ── Queries ──────────────────────────────────────────────────

    public function id(): RegistroEspecimenId
    {
        return $this->id;
    }

    public function nombreCientifico(): string
    {
        return $this->nombreCientifico;
    }

    public function nombreCorregido(): ?string
    {
        return $this->nombreCorregido;
    }

    public function estado(): EstadoRegistroEspecimen
    {
        return $this->estado;
    }

    public function esNoCatalogado(): bool
    {
        return $this->noCatalogado;
    }

    public function motivoJustificacion(): ?string
    {
        return $this->motivoJustificacion;
    }

    /** @return array<string, mixed> */
    public function datosDwC(): array
    {
        return $this->datosDwC;
    }

    /** @return list<array{campo: string, original: mixed, normalizado: mixed}> */
    public function normalizaciones(): array
    {
        return $this->normalizaciones;
    }

    // ── Reconstitución desde persistencia ────────────────────────

    /**
     * @param  array<string, mixed>  $datosDwC
     * @param  list<array{campo: string, original: mixed, normalizado: mixed}>  $normalizaciones
     */
    public static function reconstituir(
        RegistroEspecimenId $id,
        string $nombreCientifico,
        ?string $nombreCorregido,
        EstadoRegistroEspecimen $estado,
        bool $noCatalogado,
        ?string $motivoJustificacion,
        array $datosDwC = [],
        array $normalizaciones = [],
    ): self {
        $registro = new self;
        $registro->id = $id;
        $registro->nombreCientifico = $nombreCientifico;
        $registro->nombreCorregido = $nombreCorregido;
        $registro->estado = $estado;
        $registro->noCatalogado = $noCatalogado;
        $registro->motivoJustificacion = $motivoJustificacion;
        $registro->datosDwC = $datosDwC;
        $registro->normalizaciones = $normalizaciones;

        return $registro;
    }
}
