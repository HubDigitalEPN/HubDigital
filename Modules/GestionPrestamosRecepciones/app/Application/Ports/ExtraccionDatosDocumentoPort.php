<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;

interface ExtraccionDatosDocumentoPort
{
    /** @param array<string, string> $documentos [nombre => ruta] */
    public function extraerDatos(array $documentos): DatosIntegradosDocumento;
}
