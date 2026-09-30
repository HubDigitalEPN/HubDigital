<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

/**
 * Servicio de dominio puro que determina qué documentos son obligatorios para un
 * trámite de depósito o donación de especímenes.
 *
 * Combina un documento base según el tipo de trámite con documentos suplementarios
 * que dependen del origen de recolección y la situación regulatoria. En el flujo
 * nacional con permisos se adjuntan tanto la autorización de recolección como la
 * guía de movilización. Sin estado ni dependencias externas.
 */
final class ReglaDocumentacionRequerida
{
    /** Documento base obligatorio según el tipo de trámite */
    private const FORMATO_BASE = [
        // Los formularios institucionales ya no se cargan como archivos preparados
        // fuera del sistema. HubDigital los construye con los datos del expediente y
        // exige que el depositante firme el oficio electrónico en el primer paso.
        'Depósito' => [],
        'Donación' => [],
    ];

    /** Documentos suplementarios según origen y situación regulatoria */
    private const TABLA = [
        'Nacional (Ecuador)' => [
            'Posee permisos del MAE' => [
                'Copia de la autorización de recolección (MAE)',
                'Copia del permiso de movilización',
            ],
            'Sin permisos del MAE' => [
                'Documento de explicación de motivos y/o carta de justificación (institucional o personal)',
            ],
        ],
        'Exterior (Extranjero)' => [
            'Proviene de colección foránea' => [
                'Documento de procedencia de los especimenes',
            ],
        ],
    ];

    /**
     * Calcula la lista de documentos requeridos para el trámite indicado.
     *
     * @param  string  $tipoTramite  'Depósito' o 'Donación'.
     * @param  string  $origenRecoleccion  Origen de los especímenes (Nacional / Exterior).
     * @param  string  $situacionRegulatoria  Estado de permisos (con/sin MAE, colección foránea…).
     * @param  string|null  $provinciaOrigen  Provincia declarada; se conserva para trazabilidad.
     * @return string[] Nombres de los documentos requeridos, sin duplicados.
     *
     * @throws \DomainException Si no existe regla para la combinación origen/situación regulatoria.
     */
    public function determinar(string $tipoTramite, string $origenRecoleccion, string $situacionRegulatoria, ?string $provinciaOrigen = null): array
    {
        $base = self::FORMATO_BASE[$tipoTramite] ?? [];

        // Los documentos suplementarios (MAE, Movilización, etc.) solo aplican a Depósito
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

        return array_values(array_unique([...$base, ...$suplementarios]));
    }
}
