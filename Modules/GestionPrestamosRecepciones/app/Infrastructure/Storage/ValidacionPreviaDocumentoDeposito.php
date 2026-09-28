<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LocalExtraccionDatosDocumentoAdapter;

/** Inspección y lectura de la copia temporal antes de crear un objeto del expediente. */
final class ValidacionPreviaDocumentoDeposito
{
    public function __construct(
        private readonly ValidadorPdfDeposito $inspector,
        private readonly LocalExtraccionDatosDocumentoAdapter $lector,
        private readonly AnalizadorDocumentoAmbiental $analizador,
    ) {}

    /** @return array<string,mixed> */
    public function validar(string $archivo, string $nombre): array
    {
        $huella = hash_file('sha256', $archivo);
        if ($huella === false) {
            throw new DocumentoDepositoRechazado('pdf_inseguro', 'No se pudo leer el PDF seleccionado.');
        }
        try {
            $estructura = $this->inspector->inspeccionar($archivo);
        } catch (\InvalidArgumentException $error) {
            throw new DocumentoDepositoRechazado('pdf_inseguro', $error->getMessage());
        }
        $esperado = $this->analizador->tipoEsperadoParaNombre($nombre);
        $metadatos = ['sha256' => $huella, 'estructura' => $estructura, 'nombre_documento' => $nombre];
        if ($esperado !== null) {
            try {
                $lectura = $this->lector->leerArchivoLocal($archivo);
            } catch (\Throwable $error) {
                throw new DocumentoDepositoRechazado('contenido_no_legible', 'No se pudo leer el contenido del PDF. Adjunta un documento completo y legible.');
            }
            if ($lectura['procesamiento_parcial'] || trim($lectura['texto']) === '') {
                throw new DocumentoDepositoRechazado('contenido_no_legible', 'No se pudo leer el contenido completo del PDF. No es posible confirmar su tipo de documento.');
            }
            $analisis = $this->analizador->analizar($lectura['texto']);
            if (($analisis['tipo_detectado'] ?? null) !== $esperado) {
                $tipo = $esperado === AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION
                    ? 'una autorización de recolección del MAE' : 'un permiso o guía de movilización';
                throw new DocumentoDepositoRechazado('tipo_incorrecto', 'Documento de tipo incorrecto o no identificable. El contenido debe corresponder claramente a '.$tipo.'.');
            }
            $metadatos += ['tipo_detectado' => $esperado, 'analisis' => $analisis,
                'texto_sha256' => hash('sha256', $lectura['texto']), 'motor_lectura' => $lectura['motor']];
        }
        if (! hash_equals($huella, (string) hash_file('sha256', $archivo))) {
            throw new DocumentoDepositoRechazado('pdf_inseguro', 'El PDF cambió durante su lectura. Selecciona nuevamente el archivo.');
        }

        return $metadatos;
    }
}
