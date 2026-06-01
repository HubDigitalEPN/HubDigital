<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

interface PdfGeneratorPort
{
    /**
     * Genera un PDF desde una vista Blade y lo persiste en storage.
     *
     * @param  array<string, mixed>  $datos
     * @return string Ruta relativa donde se almacenó el PDF.
     */
    public function generarYAlmacenar(string $vista, array $datos, string $rutaDestino): string;

    public function almacenarImagenPng(string $base64, string $rutaDestino): void;
}
