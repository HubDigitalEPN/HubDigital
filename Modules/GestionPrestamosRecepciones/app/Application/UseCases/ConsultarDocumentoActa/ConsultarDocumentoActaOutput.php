<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa;

/**
 * Output DTO con las rutas de los documentos de un acta y la decisión de acceso.
 *
 * La existencia física del archivo en el almacenamiento la verifica la capa de
 * presentación; aquí solo se resuelve qué rutas tiene el acta y si el usuario
 * está autorizado a verlas.
 */
final readonly class ConsultarDocumentoActaOutput
{
    /**
     * @param bool $existe Indica si el acta existe.
     * @param bool $autorizado Indica si el usuario puede acceder a los documentos.
     * @param string|null $pdfRuta Ruta del PDF original del acta.
     * @param string|null $pdfFirmadoRuta Ruta del PDF/imagen firmada.
     * @param string|null $documentoIdentidadRuta Ruta del documento de identidad.
     * @param string|null $documentoExportacionRuta Ruta del documento de exportación.
     */
    public function __construct(
        public bool $existe,
        public bool $autorizado,
        public ?string $pdfRuta,
        public ?string $pdfFirmadoRuta,
        public ?string $documentoIdentidadRuta,
        public ?string $documentoExportacionRuta,
    ) {}
}
