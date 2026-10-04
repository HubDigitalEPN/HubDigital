<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

/** Decisiones acotadas a evidencia QA6; no realiza coincidencias taxonómicas aproximadas. */
final class ReconciliacionCientificaQa6
{
    public const FUENTE_TAXONOMIA = 'https://www.ars.usda.gov/research/publications/publication/?seqNo115=384801';

    public const FUENTE_ESPECIE = 'https://idtools.org/id/Anastrepha/Key/anatox/Media/Html/anfreidb.htm';

    public const FUENTE_RAGUA = 'https://www.miteco.gob.es/es/parques-nacionales-oapn/red-parques-nacionales/parques-nacionales/sierra-nevada/guia-visitante/itinerarios.html';

    public const NOTA_RAGUA = 'QA6-003: conflicto geográfico pendiente de curaduría. Sierra Nevada, Puerto de la Ragua (Granada/Almería, España, según MITECO) contradice país Ecuador y el par -4.0226841, -79.194422. Coordenadas y referencia INEC retiradas del uso estructurado; valores originales conservados. No se ha confirmado el lugar de colecta ni una coordenada de reemplazo.';

    /** @param array<string, string|null> $fila */
    public static function taxonomia(array $fila): array
    {
        if (mb_strtolower(trim($fila['genus'] ?? '')) !== 'anastrepha') return $fila;
        if (mb_strtolower(trim($fila['order'] ?? '')) === 'diptera'
            && mb_strtolower(trim($fila['family'] ?? '')) === 'tephritidae'
            && mb_strtolower(trim($fila['subfamily'] ?? '')) !== 'cassidinae') return $fila;

        // La relación del género está documentada. No se reidentifica el material.
        // Se omiten rangos intermedios de la fuente incompatible, sin inventarlos.
        return array_replace($fila, [
            'kingdom' => 'Animalia', 'phylum' => 'Arthropoda', 'class' => 'Insecta',
            'order' => 'Diptera', 'suborder' => null, 'family' => 'Tephritidae',
            'subfamily' => null, 'tribe' => null, 'genus' => 'Anastrepha',
        ]);
    }

    /** @param array<string, string|null> $fila */
    public static function notaTaxonomia(array $fila): ?string
    {
        $corregida = self::taxonomia($fila);
        if ($corregida === $fila) return null;

        $original = array_intersect_key($fila, array_flip([
            'kingdom', 'phylum', 'class', 'order', 'suborder', 'family', 'subfamily', 'tribe', 'genus',
        ]));

        return 'QA6-002: linaje de Anastrepha reconciliado con Diptera/Tephritidae; '
            .'clasificación fuente conservada: '.json_encode($original, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            .'. Fuente: '.self::FUENTE_TAXONOMIA.'. La identificación física no se ha revalidado.';
    }

    /** Identidad compuesta y valores exactos: no corrige otros registros del topónimo. */
    public static function conflictoRagua(array $fila): bool
    {
        $localidad = '';
        foreach (['localidad_verbatim', 'verbatim_locality', 'locality_name'] as $campo) {
            if (trim($fila[$campo] ?? '') !== '') { $localidad = $fila[$campo]; break; }
        }
        return trim($fila['occurrence_id'] ?? '') === 'MEPN-INV-30203'
            && trim($fila['old_code'] ?? '') === '787'
            && mb_strtolower(trim($fila['country'] ?? '')) === 'ecuador'
            && mb_strtolower(trim($fila['state_province'] ?? '')) === 'granada'
            && mb_strtolower(trim($localidad)) === 'sierra nevada, puerto de la ragua'
            && is_numeric($fila['decimal_latitude'] ?? null)
            && is_numeric($fila['decimal_longitude'] ?? null)
            && abs((float) $fila['decimal_latitude'] + 4.0226841) < 0.00000005
            && abs((float) $fila['decimal_longitude'] + 79.194422) < 0.00000005;
    }

    public static function agregarNota(?string $original, string $nota): string
    {
        if (str_contains($original ?? '', $nota)) return $original;
        return trim(($original !== null && trim($original) !== '' ? $original."\n" : '').$nota);
    }
}
