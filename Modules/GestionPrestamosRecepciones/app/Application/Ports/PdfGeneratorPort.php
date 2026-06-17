<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para generar y almacenar archivos PDF (p. ej. el
 * acta de préstamo) y guardar imágenes de firma.
 *
 * Lo implementa un adaptador en Infrastructure (p. ej. DomPDF).
 */
interface PdfGeneratorPort
{
    /**
     * Genera un PDF desde una vista Blade y lo persiste en storage.
     *
     * @param  array<string, mixed>  $datos
     * @return string Ruta relativa donde se almacenó el PDF.
     */
    public function generarYAlmacenar(string $vista, array $datos, string $rutaDestino): string;

    /**
     * Decodifica y almacena una imagen PNG en base64 (p. ej. la firma del canvas).
     *
     * @param  string  $base64  Contenido PNG en base64 (data URL o crudo).
     * @param  string  $rutaDestino  Ruta relativa donde almacenar la imagen.
     */
    public function almacenarImagenPng(string $base64, string $rutaDestino): void;
}
