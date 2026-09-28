<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

final class DocumentoDepositoRechazado extends \InvalidArgumentException
{
    public function __construct(public readonly string $estado, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
