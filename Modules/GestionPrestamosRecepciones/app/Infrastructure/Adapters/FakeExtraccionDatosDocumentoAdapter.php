<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;

final class FakeExtraccionDatosDocumentoAdapter implements ExtraccionDatosDocumentoPort
{
    private const EXTRACCIONES = [
        'Copia de la autorización de recolección (MAE)' => [
            'nroPermisoRecoleccion' => 'REC-2024-001',
        ],
        'Copia del permiso de movilización' => [
            'nroPermisoMovilizacion' => 'MOV-2024-001',
            'grupoAnimal' => 'Insectos (Orden: Lepidoptera)',
            'provinciaOrigen' => 'Pichincha',
            'localidad' => 'Quito',
        ],
        'Carta de Cesión de Derechos / Origen Lícito' => [
            'origenDonacion' => 'Donación voluntaria institucional',
        ],
    ];

    /** @param array<string, string> $documentos [nombre => ruta] */
    public function extraerDatos(array $documentos): DatosIntegradosDocumento
    {
        $merged = [
            'nroPermisoRecoleccion' => null,
            'nroPermisoMovilizacion' => null,
            'grupoAnimal' => null,
            'provinciaOrigen' => null,
            'localidad' => null,
            'origenDonacion' => null,
        ];

        foreach (array_keys($documentos) as $nombre) {
            if (isset(self::EXTRACCIONES[$nombre])) {
                $merged = array_merge($merged, self::EXTRACCIONES[$nombre]);
            }
        }

        return new DatosIntegradosDocumento(
            nroPermisoRecoleccion: $merged['nroPermisoRecoleccion'],
            nroPermisoMovilizacion: $merged['nroPermisoMovilizacion'],
            grupoAnimal: $merged['grupoAnimal'],
            provinciaOrigen: $merged['provinciaOrigen'],
            localidad: $merged['localidad'],
            origenDonacion: $merged['origenDonacion'],
        );
    }
}
