<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum EstadoRecordatorio: string
{
    case ProximoAVencer = 'próximo a vencer';
    case Vencido        = 'vencido';
}
