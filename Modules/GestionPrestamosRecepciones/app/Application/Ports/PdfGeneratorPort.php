<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para generar y almacenar el acta de préstamo en
 * PDF y guardar imágenes de firma.
 *
 * Lo implementa un adaptador en Infrastructure (p. ej. DomPDF).
 */
interface PdfGeneratorPort
{
    /**
     * Genera el acta completa: el cuerpo vertical más la hoja de especímenes en
     * horizontal, fusionadas en un único PDF.
     *
     * @param  array<string, mixed>  $datos  Datos para la vista (al menos 'acta').
     * @return string Bytes del PDF.
     */
    public function generarActa(array $datos): string;

    /**
     * Genera el acta completa ({@see generarActa}) y la persiste en storage.
     *
     * @param  array<string, mixed>  $datos
     * @return string Ruta relativa donde se almacenó el PDF.
     */
    public function generarActaYAlmacenar(array $datos, string $rutaDestino): string;

    /**
     * Decodifica y almacena una imagen PNG en base64 (p. ej. la firma del canvas).
     *
     * @param  string  $base64  Contenido PNG en base64 (data URL o crudo).
     * @param  string  $rutaDestino  Ruta relativa donde almacenar la imagen.
     */
    public function almacenarImagenPng(string $base64, string $rutaDestino): void;

    /**
     * Lee una imagen PNG previamente almacenada y la devuelve como data-URI base64
     * (p. ej. para re-incrustar la firma canvas del investigador en la plantilla).
     *
     * @param  string  $ruta  Ruta relativa de la imagen.
     * @return string|null  data-URI `data:image/png;base64,...` o null si no existe.
     */
    public function leerImagenBase64(string $ruta): ?string;
}
