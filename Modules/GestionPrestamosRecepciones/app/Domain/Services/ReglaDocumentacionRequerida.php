<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

/**
 * Servicio de dominio puro que determina qué documentos son obligatorios para un
 * trámite de depósito o donación de especímenes.
 *
 * Combina un documento base según el tipo de trámite con documentos suplementarios
 * que dependen del origen de recolección y la situación regulatoria. Aplica además
 * la excepción de Pichincha (no requiere permiso de movilización). Sin estado ni
 * dependencias externas.
 */
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
     * @param  string|null  $provinciaOrigen  Provincia; si es Pichincha se omite el permiso de movilización.
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

        if ($provinciaOrigen !== null && strtolower(trim($provinciaOrigen)) === 'pichincha') {
            $suplementarios = array_values(
                array_filter($suplementarios, fn (string $d) => $d !== 'Copia del permiso de movilización')
            );
        }

        return array_values(array_unique([...$base, ...$suplementarios]));
    }
}
