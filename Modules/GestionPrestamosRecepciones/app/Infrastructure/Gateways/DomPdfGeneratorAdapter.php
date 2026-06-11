<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Gateways;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;

/**
 * Adaptador para la generación de documentos PDF utilizando la librería DomPDF.
 *
 * Implementa {@see PdfGeneratorPort} para renderizar vistas de Laravel en archivos PDF
 * y gestionar el almacenamiento de imágenes auxiliares (como firmas).
 */
final class DomPdfGeneratorAdapter implements PdfGeneratorPort
{
    /**
     * Genera un archivo PDF a partir de una vista y lo almacena en el storage.
     *
     * @param  string  $vista  Nombre de la vista de Laravel.
     * @param  array<string, mixed>  $datos  Datos para inyectar en la vista.
     * @param  string  $rutaDestino  Ruta relativa en el storage donde se guardará el PDF.
     * @return string La ruta donde se almacenó el archivo.
     */
    public function generarYAlmacenar(string $vista, array $datos, string $rutaDestino): string
    {
        $pdf = Pdf::loadView($vista, $datos);

        Storage::put($rutaDestino, $pdf->output());

        return $rutaDestino;
    }

    /**
     * Decodifica una imagen en base64 y la almacena como un archivo PNG.
     */
    public function almacenarImagenPng(string $base64, string $rutaDestino): void
    {
        $data = base64_decode(str_replace(' ', '+', substr($base64, strpos($base64, ',') + 1)));

        Storage::put($rutaDestino, $data);
    }
}
