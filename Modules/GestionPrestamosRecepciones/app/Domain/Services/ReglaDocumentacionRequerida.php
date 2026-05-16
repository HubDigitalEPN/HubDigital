<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

final class ReglaDocumentacionRequerida
{
    /** Documento base obligatorio según el tipo de trámite */
    private const FORMATO_BASE = [
        'Depósito' => ['Formato Solicitud Depósito'],
        'Donación' => ['Formato Solicitud Donación', 'Carta de Cesión de Derechos / Origen Lícito'],
    ];

    /** Documentos suplementarios según origen y situación regulatoria */
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
    public function determinar(string $tipoTramite, string $origenRecoleccion, string $situacionRegulatoria, ?string $provinciaOrigen = null): array
    {
        $base = self::FORMATO_BASE[$tipoTramite] ?? [];

        // Los documentos suplementarios (MAATE, Movilización, etc.) solo aplican a Depósito
        if ($tipoTramite !== 'Depósito') {
            return array_values($base);
        }

        $suplementarios = self::TABLA[$origenRecoleccion][$situacionRegulatoria] ?? null;

        if ($suplementarios === null) {
            throw new \DomainException(
                sprintf(
                    'No existe regla de documentación para el origen "%s" con situación regulatoria "%s"',
                    $origenRecoleccion,
                    $situacionRegulatoria
                )
            );
        }

        if ($provinciaOrigen !== null && strtolower(trim($provinciaOrigen)) === 'pichincha') {
            $suplementarios = array_values(
                array_filter($suplementarios, fn (string $d) => $d !== 'Copia del Permiso de Movilización')
            );
        }

        return array_values(array_unique([...$base, ...$suplementarios]));
    }
}
