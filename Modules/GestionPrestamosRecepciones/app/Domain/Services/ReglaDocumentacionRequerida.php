<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

final class ReglaDocumentacionRequerida
{
    /** Documento base obligatorio según el tipo de trámite */
    private const FORMATO_BASE = [
        'Depósito' => ['Formato solicitud depósito'],
        'Donación' => ['Formato solicitud donación', 'Carta de cesión de derechos / origen lícito'],
    ];

    /** Documentos suplementarios según origen y situación regulatoria */
    private const TABLA = [
        'Nacional (Ecuador)' => [
            'Posee permisos del MAATE' => [
                'Copia de la autorización de recolección (MAATE)',
                'Copia del permiso de movilización',
            ],
            'Sin permisos del MAATE' => [
                'Documento de explicación de motivos y/o carta de justificación (institucional o personal)',
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
                array_filter($suplementarios, fn (string $d) => $d !== 'Copia del permiso de movilización')
            );
        }

        return array_values(array_unique([...$base, ...$suplementarios]));
    }
}
