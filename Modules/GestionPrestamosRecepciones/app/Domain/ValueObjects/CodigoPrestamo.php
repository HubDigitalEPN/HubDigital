<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object inmutable que representa el código de negocio legible y único que
 * acompaña a un préstamo durante todo su ciclo de vida (solicitud → acta →
 * préstamo), con formato `MEPN-INV-LOAN-{AÑO}-{SECUENCIA}`.
 *
 * El año proviene de la creación de la solicitud (informativo) y la secuencia es
 * un correlativo global continuo a 4 dígitos (no se reinicia entre años). Ej:
 * `MEPN-INV-LOAN-2026-0001`.
 *
 * Es un VO puro: solo valida y compone el string. NO se autogenera — el siguiente
 * número correlativo lo provee el Port {@see \Modules\GestionPrestamosRecepciones\Application\Ports\GeneradorCodigoPrestamo}
 * implementado en infraestructura (necesita estado persistido). Construir vía
 * {@see self::fromParts()} y rehidratar desde BD vía {@see self::fromString()}.
 */
final readonly class CodigoPrestamo
{
    private const PREFIX = 'MEPN-INV-LOAN-';

    /** Dígitos mínimos de la secuencia; crece si algún día se superan los 9999. */
    private const SECUENCIA_PADDING = 4;

    /** Formato actual: MEPN-INV-LOAN-AAAA-NNNN (secuencia de 4+ dígitos). */
    private const PATTERN = '/^MEPN-INV-LOAN-\d{4}-\d{4,}$/';

    /**
     * Formatos legados aceptados al reconstruir registros previos al backfill:
     * MEPN-INV-XXXXXX / sol_XXXXXX (solicitud) y ACT_XXXXXX (acta/préstamo).
     */
    private const PATTERN_LEGADO = '/^(MEPN-INV-[A-Z0-9]{6}|sol_[A-Z0-9]{6}|ACT_[A-Z0-9]{6})$/';

    private function __construct(private string $value) {}

    /**
     * Compone el código a partir del año y el número correlativo global.
     *
     * @throws InvalidArgumentException Si el año no tiene 4 dígitos o la secuencia no es positiva.
     */
    public static function fromParts(int $anio, int $secuencia): self
    {
        if ($anio < 1000 || $anio > 9999) {
            throw new InvalidArgumentException("Año inválido para CodigoPrestamo: '{$anio}'. Se esperaban 4 dígitos.");
        }

        if ($secuencia < 1) {
            throw new InvalidArgumentException("Secuencia inválida para CodigoPrestamo: '{$secuencia}'. Debe ser un entero positivo.");
        }

        $sufijo = str_pad((string) $secuencia, self::SECUENCIA_PADDING, '0', STR_PAD_LEFT);

        return new self(self::PREFIX.$anio.'-'.$sufijo);
    }

    /**
     * Reconstruye el código desde su representación textual (formato actual o legado).
     *
     * @throws InvalidArgumentException Si el valor no coincide con ningún formato conocido.
     */
    public static function fromString(string $value): self
    {
        if (! preg_match(self::PATTERN, $value) && ! preg_match(self::PATTERN_LEGADO, $value)) {
            throw new InvalidArgumentException(
                "Formato inválido para CodigoPrestamo: '{$value}'. Se esperaba 'MEPN-INV-LOAN-AAAA-NNNN'."
            );
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
