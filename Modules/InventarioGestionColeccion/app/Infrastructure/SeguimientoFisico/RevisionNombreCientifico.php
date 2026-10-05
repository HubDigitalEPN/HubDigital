<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Evidencia curatorial interna; no modifica determinaciones ni consulta proveedores. */
final class RevisionNombreCientifico
{
    public const TABLA = 'taxonomia.revision_nombres_cientificos';

    public const ETIQUETAS = [
        'reconocido' => 'Nombre reconocido',
        'corregido' => 'Grafía corregida',
        'historico' => 'Sinónimo o combinación histórica',
        'vacio' => 'Nombre vacío',
        'no_cientifico' => 'Texto no científico',
        'no_resuelto' => 'Sin coincidencia suficiente',
        'grafia_por_revisar' => 'Posible error de escritura',
        'codigo_local' => 'Código local o morfoespecie',
        'calificado' => 'Identificación calificada o anotada',
        'clasificacion_por_revisar' => 'Linaje o rango por revisar',
        'fuente_no_disponible' => 'Fuente no disponible',
        'sin_revisar' => 'Sin revisar',
        'desactualizado' => 'Volver a revisar: nombre modificado',
    ];

    public const PENDIENTES = [
        'vacio', 'no_cientifico', 'no_resuelto', 'grafia_por_revisar',
        'codigo_local', 'calificado', 'clasificacion_por_revisar', 'fuente_no_disponible',
    ];

    /** @param list<array<string, mixed>> $filas */
    public function completar(array $filas): array
    {
        if ($filas === []) return [];
        $evidencias = Schema::hasTable(self::TABLA)
            ? DB::table(self::TABLA)->whereIn('especimen_id', array_column($filas, 'id'))
                ->get(['especimen_id', 'taxon_id', 'nombre_original', 'nombre_revisado', 'taxon_verbatim_original', 'estado', 'motivo', 'candidato', 'fuente', 'consultado_en'])->keyBy('especimen_id')
            : collect();

        return array_map(static function (array $fila) use ($evidencias): array {
            $revision = $evidencias->get($fila['id']);
            $nombre = $fila['taxonNombre'] ?? $fila['taxonVerbatim'] ?? null;
            $vigente = $revision !== null
                && $revision->taxon_id === ($fila['taxonId'] ?? null)
                && $revision->nombre_revisado === $nombre
                && $revision->taxon_verbatim_original === ($fila['taxonVerbatim'] ?? null);
            $estado = $revision === null ? 'sin_revisar' : ($vigente ? $revision->estado : 'desactualizado');

            return $fila + [
                'revisionNombreCientifico' => self::ETIQUETAS[$estado] ?? 'Sin revisar',
                'motivoNombreCientifico' => $vigente ? $revision->motivo : 'Revisar el nombre actual antes de usar el contraste anterior.',
                'nombreCientificoOriginal' => $revision?->nombre_original,
                'candidatoNombreCientifico' => $vigente ? $revision->candidato : null,
                'fuenteNombreCientifico' => $revision?->fuente,
                'fechaRevisionNombreCientifico' => $revision?->consultado_en,
            ];
        }, $filas);
    }
}
