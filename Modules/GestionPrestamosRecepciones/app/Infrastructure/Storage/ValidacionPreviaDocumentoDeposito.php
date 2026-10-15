<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;
use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorCartaDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LocalExtraccionDatosDocumentoAdapter;
use Illuminate\Support\Str;

/** Inspección y lectura de la copia temporal antes de crear un objeto del expediente. */
final class ValidacionPreviaDocumentoDeposito
{
    public function __construct(
        private readonly ValidadorPdfDeposito $inspector,
        private readonly LocalExtraccionDatosDocumentoAdapter $lector,
        private readonly AnalizadorDocumentoAmbiental $analizador,
    ) {}

    /** @return array<string,mixed> */
    public function validar(string $archivo, string $nombre, array $expediente = [], bool $confirmarContenidoIncierto = false): array
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
        $analizadorCarta = new AnalizadorCartaDeposito;
        $cartaEsperada = $analizadorCarta->tipoEsperado($nombre);
        $metadatos = ['sha256' => $huella, 'estructura' => $estructura, 'nombre_documento' => $nombre];
        if ($esperado !== null || $cartaEsperada !== null) {
            try {
                $lectura = $this->lector->leerArchivoLocal($archivo);
            } catch (\Throwable $error) {
                return $this->contenidoIncierto($metadatos, 'No se pudo leer el contenido del PDF.', $confirmarContenidoIncierto);
            }
            if ($lectura['procesamiento_parcial'] || trim($lectura['texto']) === '') {
                return $this->contenidoIncierto($metadatos, 'No se pudo leer el contenido completo del PDF.', $confirmarContenidoIncierto);
            }
            $analisis = $this->analizador->analizar($lectura['texto']);
            if ($cartaEsperada !== null) {
                $carta = $analizadorCarta->analizar($lectura['texto'], $cartaEsperada, $expediente);
                if (! $carta['tipo_compatible'] || ($analisis['tipo_detectado'] ?? AnalizadorDocumentoAmbiental::DESCONOCIDO) !== AnalizadorDocumentoAmbiental::DESCONOCIDO) {
                    throw new DocumentoDepositoRechazado('tipo_incorrecto', 'El contenido no corresponde claramente a la carta solicitada. Incluye su propósito, la procedencia del material y la explicación o declaración correspondiente.');
                }
                if ($carta['discrepancias'] !== []) throw new DocumentoDepositoRechazado('tipo_incorrecto', $carta['discrepancias'][0]);
                $metadatos += ['tipo_detectado' => $cartaEsperada, 'analisis_carta' => $carta];
            } elseif (($analisis['tipo_detectado'] ?? null) !== $esperado) {
                $tipo = $esperado === AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION
                    ? 'una autorización de recolección del MAE' : 'un permiso o guía de movilización';
                if (($analisis['tipo_detectado'] ?? null) === AnalizadorDocumentoAmbiental::DESCONOCIDO
                    && ! $this->esDocumentoDeOtroTipo($lectura['texto'], $esperado)) {
                    return $this->contenidoIncierto($metadatos + ['analisis' => $analisis,
                        'texto_sha256' => hash('sha256', $lectura['texto']), 'motor_lectura' => $lectura['motor']],
                        'No se pudo identificar el contenido como '.$tipo.'.', $confirmarContenidoIncierto);
                }
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

    private function contenidoIncierto(array $metadatos, string $motivo, bool $confirmado): array
    {
        if (! $confirmado) throw new DocumentoDepositoRechazado('contenido_incierto', $motivo);
        return $metadatos + ['tipo_detectado' => AnalizadorDocumentoAmbiental::DESCONOCIDO,
            'revision_contenido' => ['estado' => 'pendiente', 'confirmada_por_usuario' => true, 'motivo' => $motivo]];
    }

    /** El propósito explícito de una solicitud o un oficio distinto no es incertidumbre. */
    private function esDocumentoDeOtroTipo(string $texto, string $esperado): bool
    {
        $normal = Str::lower(Str::ascii($texto));
        if (preg_match('/(?:^|\n)\s*(?:asunto\s*:\s*)?(?:solicitud[^\r\n]{0,100}(?:deposito|ingreso de (?:especimenes|muestras))|(?:deposito|entrega)\s+de\s+especimenes)|\b(?:solicito|solicitamos|solicita|permito solicitar)[\s\S]{0,200}\b(?:deposito|ingreso|recepcion|recepten)[\s\S]{0,100}\b(?:especimenes|muestras|coleccion|ejemplares)\b|\basunto\s*:\s*autorizacion\s+de\s+equipo\s+tecnico/', $normal) === 1) return true;
        return $esperado === AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION
            && preg_match('/\basunto\s*:[^\r\n]{0,140}\b(?:guia|autorizacion)\s+de\s+movilizaci[oó]n/', $normal) === 1;
    }
}
