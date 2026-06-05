<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Gateways;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;

final class DomPdfGeneratorAdapter implements PdfGeneratorPort
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function generarYAlmacenar(string $vista, array $datos, string $rutaDestino): string
    {
        $pdf = Pdf::loadView($vista, $datos);

        Storage::put($rutaDestino, $pdf->output());

        return $rutaDestino;
    }

    public function almacenarImagenPng(string $base64, string $rutaDestino): void
    {
        $data = base64_decode(str_replace(' ', '+', substr($base64, strpos($base64, ',') + 1)));

        Storage::put($rutaDestino, $data);
    }
}
