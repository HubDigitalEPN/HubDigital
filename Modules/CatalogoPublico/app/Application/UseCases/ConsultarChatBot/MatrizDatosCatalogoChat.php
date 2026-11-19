<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Modules\CatalogoPublico\Infrastructure\LocalidadPublica;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

/** Datos tabulares determinísticos; nunca delega los valores publicados a una IA. */
final class MatrizDatosCatalogoChat
{
    public function solicitada(string $pregunta): bool
    {
        $normal = rtrim(app(TextoChat::class)->normalizar($pregunta), '?.! ');
        // Solo vocabulario de intención: no corrige nombres, códigos ni filtros.
        $normal = preg_replace('/\btabal\b/', 'tabla', $normal) ?? $normal;
        $normal = preg_replace('/\bdtaos\b/', 'datos', $normal) ?? $normal;
        if (preg_match('/^(?:que (?:es|son|significa)|como|donde|para que)\b|\b(?:descarg\w*|export\w*|csv|xlsx|excel|pasos)\b/', $normal)) return false;
        // «Cuantos ... en una tabla» pide conteos. «Genera una matriz ... e
        // indica cuantos coinciden» sigue pidiendo las filas y su total.
        if (preg_match('/\b(?:matriz|tabla)\b/', $normal, $formato, PREG_OFFSET_CAPTURE)
            && preg_match('/\b(?:cuant[oa]s?|cuenta|contar|contabiliza)\b/', $normal, $conteo, PREG_OFFSET_CAPTURE)
            && $conteo[0][1] < $formato[0][1]) return false;

        return (bool) (preg_match('/\b(?:matriz|tabla)\b/', $normal)
                && (preg_match('/\b(?:datos|especies|registros|ejemplares|especimenes|catalogo|coleccion|portal|seleccion)\b/', $normal)
                    || preg_match('/\b(?:haz|has|dame|muestra(?:me)?|quiero|necesito|genera|crea|prepara|construye)\b/', $normal)))
            || (bool) preg_match('/^(?:y )?(?:(?:haz|has|dame|muestra(?:me)?|quiero|necesito|ponlos|presentalos|organizalos)\s+)?(?:(?:en )?(?:una|la)\s+)?(?:matriz|tabla)(?: de datos)?$/', $normal)
            || (bool) preg_match('/\b(?:dame|muestra(?:me)?|mostrar|quiero|necesito)\b.*\bdatos\b.*\b(?:catalogo|coleccion|portal|seleccion)\b/', $normal);
    }

    public function responder(SeleccionPaginaChat $seleccion, array $entidades, string $alcance): array
    {
        $base = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($seleccion->filtros,
            $seleccion->parametros['nivel'] ?? '', $seleccion->parametros['taxon'] ?? '');
        $total = (clone $base)->count('te.id');
        $filas = $base->leftJoin('taxonomia.taxones as t', 't.id', '=', 'te.taxon_id')
            ->selectRaw('CASE WHEN ed.occurrence_id_visible THEN te.occurrence_id END AS codigo,
                CASE WHEN ed.scientific_name_visible THEN t.nombre_cientifico END AS nombre,
                CASE WHEN ed.event_date_visible THEN te.fecha_colecta END AS fecha,
                CASE WHEN ed.recorded_by_visible THEN te.colector END AS colector,
                CASE WHEN ed.country_visible THEN te.country END AS pais,
                CASE WHEN ed.state_province_visible THEN te.state_province END AS provincia,
                CASE WHEN ed.locality_name_visible THEN '.LocalidadPublica::sql('te').' END AS localidad')
            ->orderBy('te.fila_origen_excel')->orderBy('te.id')->limit(10)->get()
            ->map(static fn (object $fila): array => (array) $fila)->all();
        $texto = $total === 0 ? 'No hay registros públicos que coincidan '.$alcance.'.'
            : 'Matriz de datos públicos '.$alcance.': '.count($filas).' de '.$total.' registros. Cada fila corresponde a un registro; la identificación puede llegar a especie u otro rango. Los campos no divulgados o sin dato aparecen como «—».';
        if ($total > 10) $texto .= ' Se muestran los primeros 10; abre los registros para consultar el resto de la misma selección.';

        $respuesta = ['texto' => $texto, 'intent' => 'catalogo.matriz_datos', 'fuente' => 'catalogo',
            'confianza' => 'HIGH', 'confianza_valor' => 1.0, 'entidades' => $entidades,
            'datos' => ['total' => $total, 'filas' => $filas],
            'opciones' => [['label' => 'Ver todos los registros consultados', 'url' => route('portal.catalogo', array_replace($seleccion->parametros, ['vista' => 'registros']))]]];
        if ($filas !== []) $respuesta['tabla'] = ['titulo' => 'Matriz de datos públicos', 'columnas' => ['codigo' => 'Código', 'nombre' => 'Identificación', 'fecha' => 'Fecha de colecta', 'colector' => 'Colector', 'pais' => 'País', 'provincia' => 'Provincia', 'localidad' => 'Localidad'], 'filas' => $filas];
        return $respuesta;
    }
}
