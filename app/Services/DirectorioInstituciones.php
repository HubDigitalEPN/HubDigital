<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\NormalizadorNombreCatalogo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante\ActualizarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEntidadDepositante\ActualizarEntidadDepositanteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEntidadDepositante\RegistrarEntidadDepositanteInput;

/** Una edición para ambos usos; los identificadores y relaciones existentes se conservan. */
final class DirectorioInstituciones
{
    public function consulta(): Builder
    {
        $instituciones = DB::table('usuarios.instituciones_catalogo')
            ->selectRaw('lower(btrim(nombre)) AS clave, min(id) AS institucion_id')
            ->groupByRaw('lower(btrim(nombre))')->havingRaw('count(*) = 1');
        $entidades = DB::table('taxonomia.entidades_depositantes')
            ->selectRaw('lower(btrim(nombre)) AS clave, min(id::text)::uuid AS entidad_id')
            ->groupByRaw('lower(btrim(nombre))')->havingRaw('count(*) = 1');
        $pares = DB::query()->fromSub($instituciones, 'iu')->joinSub($entidades, 'eu', 'iu.clave', '=', 'eu.clave')
            ->select('iu.institucion_id', 'eu.entidad_id');
        $portal = DB::table('usuarios.instituciones_catalogo as i')
            ->leftJoinSub($pares, 'p', 'p.institucion_id', '=', 'i.id')
            ->leftJoin('taxonomia.entidades_depositantes as e', 'e.id', '=', 'p.entidad_id')
            ->selectRaw("'p:' || i.id::text || ':' || coalesce(e.id::text, '') AS id, i.id::bigint AS institucion_id, e.id AS entidad_id, i.nombre, i.nombre AS institucion_nombre, e.nombre AS entidad_nombre, e.tipo, e.contacto, i.activo, i.updated_at AS portal_actualizado, e.updated_at AS entidad_actualizada,
                CASE WHEN i.activo THEN 'Disponible' ELSE 'Desactivado' END AS estado,
                CASE WHEN e.id IS NOT NULL THEN 'Disponible' ELSE 'Sin habilitar' END AS coleccion,
                CASE WHEN e.tipo = 'persona' THEN 'Persona' WHEN e.tipo IS NULL AND e.id IS NOT NULL THEN 'Sin clasificar' ELSE 'Institución' END AS tipo_label");
        $coleccion = DB::table('taxonomia.entidades_depositantes as e')
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->fromSub($pares, 'p')->whereColumn('p.entidad_id', 'e.id'))
            ->selectRaw("'c:' || e.id::text AS id, NULL::bigint AS institucion_id, e.id AS entidad_id, e.nombre, NULL::text AS institucion_nombre, e.nombre AS entidad_nombre, e.tipo, e.contacto, false AS activo, NULL::timestamp AS portal_actualizado, e.updated_at AS entidad_actualizada,
                'Sin habilitar'::text AS estado, 'Disponible'::text AS coleccion,
                CASE WHEN e.tipo = 'persona' THEN 'Persona' WHEN e.tipo IS NULL THEN 'Sin clasificar' ELSE 'Institución' END AS tipo_label");
        return DB::query()->fromSub($portal->unionAll($coleccion), 'directorio');
    }

    public function revision(object $fila): string
    {
        return hash('sha256', json_encode([$fila->institucion_id, $fila->entidad_id, $fila->institucion_nombre,
            $fila->entidad_nombre, $fila->tipo, $fila->contacto, $fila->activo, $fila->portal_actualizado,
            $fila->entidad_actualizada], JSON_THROW_ON_ERROR));
    }

    public function guardar(?string $filaId, string $revision, array $datos): string
    {
        return DB::transaction(function () use ($filaId, $revision, $datos): string {
            DB::statement("SET LOCAL lock_timeout = '5s'");
            $fila = $filaId ? $this->consulta()->where('id', $filaId)->first() : null;
            if ($filaId && !$fila) $this->conflicto();
            $nombre = trim($datos['nombre']);
            $cambioNombre = !$fila || $nombre !== $fila->nombre;
            if ($fila && !$fila->institucion_id && $datos['solicitudes'] && !$cambioNombre) {
                $canonico = NormalizadorNombreCatalogo::desde('institucion', $nombre);
                if ($canonico !== $nombre) throw ValidationException::withMessages(['nombre'=>'Para habilitar las solicitudes, confirma el nombre completo: '.$canonico]);
            }
            if ((!$fila && $datos['solicitudes']) || ($fila && $cambioNombre && ($fila->institucion_id || $datos['solicitudes']))) {
                $nombre = NormalizadorNombreCatalogo::desde('institucion', $nombre);
            }
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['directorio-instituciones:'.mb_strtolower($nombre)]);
            if ($fila) {
                if ($fila->institucion_id) DB::table('usuarios.instituciones_catalogo')->where('id',$fila->institucion_id)->lockForUpdate()->first();
                if ($fila->entidad_id) DB::table('taxonomia.entidades_depositantes')->where('id',$fila->entidad_id)->lockForUpdate()->first();
                $actual = $this->consulta()->where('id',$filaId)->first();
                if (!$actual || !hash_equals($revision, $this->revision($actual))) $this->conflicto();
            }
            if ((!$fila || $cambioNombre) && $this->consulta()->whereRaw('lower(btrim(nombre)) = ?', [mb_strtolower($nombre)])
                ->when($filaId, fn (Builder $q) => $q->where('id','!=',$filaId))->exists()) {
                throw ValidationException::withMessages(['nombre'=>'Este nombre ya está registrado. Busca su entrada y edítala para habilitar el uso que necesites.']);
            }
            if ($fila?->institucion_id || $datos['solicitudes']) {
                if (mb_strlen($nombre)>160) throw ValidationException::withMessages(['nombre'=>'El nombre usado en solicitudes admite hasta 160 caracteres.']);
                $valores=['nombre'=>$cambioNombre ? $nombre : ($fila->institucion_nombre ?? NormalizadorNombreCatalogo::desde('institucion',$nombre)), 'activo'=>$datos['solicitudes'],'updated_at'=>now()];
                if ($fila?->institucion_id) DB::table('usuarios.instituciones_catalogo')->where('id',$fila->institucion_id)->update($valores);
                else DB::table('usuarios.instituciones_catalogo')->insert($valores+['created_at'=>now()]);
            }
            if ($fila?->entidad_id || $datos['coleccion']) {
                $nombreEntidad=$cambioNombre ? $nombre : ($fila->entidad_nombre ?? $nombre);
                if ($fila?->entidad_id) {
                    app(ActualizarEntidadDepositanteHandler::class)->handle(new ActualizarEntidadDepositanteInput(
                        entidadId:$fila->entidad_id,nombre:$nombreEntidad,tipo:$datos['tipo'] ?: null,contacto:$datos['contacto'] ?: null));
                } else {
                    if (DB::table('taxonomia.entidades_depositantes')->whereRaw('lower(btrim(nombre)) = ?', [mb_strtolower($nombreEntidad)])->exists()) {
                        throw ValidationException::withMessages(['nombre'=>'Hay depositantes con este nombre que requieren revisión. Edita la entrada de la colección para conservar sus referencias.']);
                    }
                    app(RegistrarEntidadDepositanteHandler::class)->handle(new RegistrarEntidadDepositanteInput(
                        nombre:$nombreEntidad,tipo:$datos['tipo'] ?: null,contacto:$datos['contacto'] ?: null));
                }
            }
            return $nombre;
        });
    }

    private function conflicto(): never
    {
        throw ValidationException::withMessages(['nombre'=>'Esta entrada cambió mientras la editabas. Cierra el formulario, vuelve a abrirla y revisa los datos actuales.']);
    }
}
