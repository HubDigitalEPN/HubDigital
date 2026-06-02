<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;

final class FakePdfGeneratorAdapter implements PdfGeneratorPort
{
    /** @var list<array{vista: string, datos: array<string, mixed>, rutaDestino: string}> */
    private array $llamadas = [];

    /**
     * @param  array<string, mixed>  $datos
     */
    public function generarYAlmacenar(string $vista, array $datos, string $rutaDestino): string
    {
        $this->llamadas[] = [
            'vista' => $vista,
            'datos' => $datos,
            'rutaDestino' => $rutaDestino,
        ];

        return $rutaDestino;
    }

    public function almacenarImagenPng(string $base64, string $rutaDestino): void
    {
        // no-op en tests
    }

    public function fueInvocado(): bool
    {
        return count($this->llamadas) > 0;
    }

    /**
     * @return list<array{vista: string, datos: array<string, mixed>, rutaDestino: string}>
     */
    public function llamadas(): array
    {
        return $this->llamadas;
    }
}
