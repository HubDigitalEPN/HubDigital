<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Tipo de recordatorio de devolución a notificar: próximo a vencer (dentro de la
 * cadencia configurada) o vencido (plazo superado).
 */
enum EstadoRecordatorio: string
{
    case ProximoAVencer = 'próximo a vencer';
    case Vencido        = 'vencido';
}
