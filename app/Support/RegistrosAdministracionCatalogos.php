<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\DirectorioInstituciones;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;

final class RegistrosAdministracionCatalogos
{
    /** @param array<string, string> $nombres Catálogos autorizados para la cuenta. */
    public function consultar(array $nombres, string $busqueda): Builder
    {
        $registros = null;
        foreach ($nombres as $tabla => $nombre) {
            $fuente = $this->fuente($tabla, $nombre)->selectRaw('?::text AS tabla, ?::text AS catalogo', [$tabla, $nombre]);
            $registros = $registros === null ? $fuente : $registros->unionAll($fuente);
        }

        return DB::query()->fromSub($registros, 'registros_admin')
            ->when(trim($busqueda) !== '', function (Builder $query) use ($busqueda): void {
                $patron = '%'.strtr(trim($busqueda), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';
                $query->where(function (Builder $campos) use ($patron): void {
                    $campos->where('nombre', 'ilike', $patron)
                        ->orWhere('detalle', 'ilike', $patron)
                        ->orWhere('catalogo', 'ilike', $patron);
                });
            });
    }

    private function fuente(string $tabla, string $nombre): Builder
    {
        return match ($tabla) {
            'instituciones' => app(DirectorioInstituciones::class)->consulta()
                ->selectRaw("id, nombre, concat_ws(' · ', tipo_label, contacto, 'Colección: ' || coleccion) AS detalle, estado, institucion_id::text AS estado_id, entidad_id::text AS acta_id"),
            'cargos' => $this->filas('usuarios.cargos_catalogo', 'id', 'nombre', "''", $this->estadoActivo()),
            'grupos-animales' => $this->filas('recepciones.catalogo_grupos_invertebrados', 'codigo', 'nombre', "concat_ws(' · ', codigo, rango_referencia)", $this->estadoActivo()),
            'taxones' => $this->filas('taxonomia.taxones', 'id', 'nombre_cientifico', "concat_ws(' · ', rango, autor)", "initcap(estado)"),
            'localidades-coleccion' => $this->filas('taxonomia.localidades', 'id', 'nombre_canonico', "concat_ws(' · ', rango, country, state_province)", "''"),
            'localidades-ecuador' => $this->filas(CatalogoLocalidadesEcuador::TABLA, 'codigo', 'nombre', "concat_ws(' · ', canton, parroquia)", $this->estadoActivo()),
            'fuentes-revocacion' => $this->filas('recepciones.fuentes_revocacion_firma', 'codigo', 'entidad', 'patron_emisor', $this->estadoActivo()),
            'matriz-depositos' => DB::query()->fromRaw(
                'jsonb_to_recordset(?::jsonb) AS matriz(campo text, cabecera text, requisito text)',
                [json_encode(app(EsquemaMatrizPort::class)->leer()['columnas'], JSON_THROW_ON_ERROR)],
            )->selectRaw('campo AS id, cabecera AS nombre, campo AS detalle, initcap(requisito) AS estado, NULL::text AS estado_id, NULL::text AS acta_id'),
            default => DB::query()->selectRaw("'configuracion'::text AS id, ?::text AS nombre, ''::text AS detalle, ''::text AS estado, NULL::text AS estado_id, NULL::text AS acta_id", [$nombre]),
        };
    }

    private function filas(string $tabla, string $id, string $nombre, string $detalle, string $estado): Builder
    {
        return DB::table($tabla)->selectRaw(
            "$id::text AS id, ($nombre)::text AS nombre, ($detalle)::text AS detalle, ($estado)::text AS estado, NULL::text AS estado_id, NULL::text AS acta_id",
        );
    }

    private function estadoActivo(): string
    {
        return "CASE WHEN activo THEN 'Activo' ELSE 'Inactivo' END";
    }
}
