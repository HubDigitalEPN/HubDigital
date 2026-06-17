<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;

/**
 * Puerto de la capa de aplicación para extraer datos estructurados desde la
 * documentación oficial adjunta (permisos, provincia, etc.).
 *
 * Lo implementan adaptadores en Infrastructure (p. ej. extracción vía IA o fake para
 * pruebas).
 */
interface ExtraccionDatosDocumentoPort
{
    /** @param array<string, string> $documentos [nombre => ruta] */
    public function extraerDatos(array $documentos): DatosIntegradosDocumento;
}
