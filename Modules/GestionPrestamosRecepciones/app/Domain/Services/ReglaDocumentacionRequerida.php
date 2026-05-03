<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

final class ReglaDocumentacionRequerida
{
    private const TABLA = [
        'Nacional (Ecuador)' => [
            'Posee permisos del MAATE' => [
                'Copia de la Autorización de Recolección (MAATE)',
                'Copia del Permiso de Movilización',
            ],
            'Sin permisos del MAATE' => [
                'Documento de Explicación de Motivos y/o Carta de Justificación (Institucional o Personal)',
            ],
        ],
        'Exterior (Extranjero)' => [
            'Proviene de colección foránea' => [
                'Carta de Procedencia firmada por el responsable de la colección de origen',
            ],
        ],
    ];

    /** @return string[] */
    public function determinar(string $origenRecoleccion, string $situacionRegulatoria): array
    {
        $documentos = self::TABLA[$origenRecoleccion][$situacionRegulatoria] ?? null;

        if ($documentos === null) {
            throw new \DomainException(
                sprintf(
                    'No existe regla de documentación para el origen "%s" con situación regulatoria "%s"',
                    $origenRecoleccion,
                    $situacionRegulatoria
                )
            );
        }

        return $documentos;
    }
}
