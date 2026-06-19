<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

use Random\RandomException;

/**
 * Value object inmutable que representa el Código QR único que rotula el lote
 * físico de muestras tras la aprobación documental de una solicitud.
 *
 * Es un identificador de negocio legible por escáner con prefijo semántico
 * (formato `LOTE-A8B39C`). El valor/código pertenece al dominio; la generación de
 * la imagen/PNG del QR es responsabilidad de Infraestructura.
 *
 * Generar uno nuevo con {@see generar()} o reconstruir desde texto con {@see from()}.
 */
final readonly class CodigoQRLote
{
    private const PREFIJO = 'LOTE-';

    private const LONGITUD_SUFIJO = 6;

    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private function __construct(private string $value) {}

    /**
     * Reconstruye el código desde su representación textual.
     *
     * @throws \DomainException Si el valor no coincide con el formato `LOTE-A8B39C`.
     */
    public static function from(string $codigo): self
    {
        if (! preg_match('/^LOTE-[A-Z0-9]{6}$/', $codigo)) {
            throw new \DomainException(
                sprintf('"%s" no es un Código QR de lote válido. Formato esperado: LOTE-A8B39C', $codigo)
            );
        }

        return new self($codigo);
    }

    /**
     * Genera un nuevo Código QR único con sufijo alfanumérico aleatorio.
     *
     * @throws RandomException Si no hay una fuente de aleatoriedad disponible.
     */
    public static function generar(): self
    {
        $sufijo = '';
        $maximo = strlen(self::ALFABETO) - 1;

        for ($i = 0; $i < self::LONGITUD_SUFIJO; $i++) {
            $sufijo .= self::ALFABETO[random_int(0, $maximo)];
        }

        return new self(self::PREFIJO.$sufijo);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
