<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaRecepcionFirmada;

/**
 * Datos de entrada para adjuntar el acta de recepción firmada electrónicamente.
 *
 * La capa de presentación almacena el PDF subido y provee tanto la ruta relativa
 * (referencia persistida en el agregado) como la absoluta (necesaria para verificar
 * la firma con pdfsig).
 */
final readonly class SubirActaRecepcionFirmadaInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $rutaRelativa,
        public string $rutaAbsoluta,
    ) {}
}
