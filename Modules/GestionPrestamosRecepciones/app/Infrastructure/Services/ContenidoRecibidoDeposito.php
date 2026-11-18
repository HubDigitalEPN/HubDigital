<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\FilaCatalogoMapper;

/** Lectura del contenido cerrado por el Técnico. No modifica la matriz declarada. */
final class ContenidoRecibidoDeposito
{
    public function detalle(string $solicitudId): ?array
    {
        $solicitud = DB::table('recepciones.solicitudes_deposito')->where('id', $solicitudId)->first();
        if (!$solicitud || !$solicitud->contenido_validado_en) return null;
        $filas = DB::table('recepciones.contenido_recepcion')->where('solicitud_id', $solicitudId)->orderBy('created_at')->orderBy('id')->get();
        if ($filas->isEmpty()) throw new \DomainException('El contenido validado no conserva sus registros de recepción.');
        return ['proyecto' => $solicitud->solicitud_proyecto, 'individuos' => $filas->sum('cantidad'), 'registros' => $filas->map(static fn ($fila): array => [
            'id' => $fila->id, 'identificador' => $fila->identificador, 'cantidad' => (int)$fila->cantidad,
            'datos' => json_decode($fila->datos_dwc, true, 512, JSON_THROW_ON_ERROR), 'observaciones' => $fila->observaciones ?? '',
        ])->all()];
    }

    /** Los expedientes históricos sin esta etapa mantienen su ingreso original. */
    public function paraIngreso(string $solicitudId, array $declaradas): array
    {
        $contenido = $this->detalle($solicitudId);
        if ($contenido === null) return $declaradas;
        $mapper = new FilaCatalogoMapper;
        $fuentes = [];
        foreach ($declaradas as $fila) {
            $datos = $mapper->normalizarClaves($fila['datosDwC']);
            $claves = array_filter([$fila['identificadorEstable'], $datos['record_number'] ?? '', $datos['catalog_number'] ?? '', $datos['occurrence_id'] ?? '']);
            foreach (array_unique($claves) as $clave) $fuentes[mb_strtolower(trim($clave))][$fila['identificadorEstable']] = $fila;
        }
        $salida = [];
        foreach ($contenido['registros'] as $registro) {
            $coincidencias = [];
            foreach ([$registro['identificador'], $registro['datos']['recordNumber'] ?? ''] as $clave) {
                $coincidencias += $fuentes[mb_strtolower(trim($clave))] ?? [];
            }
            // Sólo una coincidencia inequívoca aporta metadatos de la matriz.
            $declarada = count($coincidencias) === 1 ? reset($coincidencias) : null;
            $datos = $declarada ? $mapper->normalizarClaves($declarada['datosDwC']) : [];
            $nombreAnterior = trim((string)($datos['scientific_name'] ?? ''));
            $datos['scientific_name'] = trim($registro['datos']['scientificName']);
            $datos['taxon_verbatim'] = $datos['scientific_name'];
            $datos['individual_count'] = $registro['cantidad'];
            foreach (['recordNumber' => 'record_number', 'preparations' => 'preparations'] as $origen => $destino) {
                if (trim($registro['datos'][$origen] ?? '') !== '') $datos[$destino] = trim($registro['datos'][$origen]);
            }
            $nota = 'Recepción '.$registro['identificador'].': '.trim($registro['observaciones']);
            if (!$declarada) $nota .= ' Sin asociación inequívoca con la matriz declarada; revisar los metadatos de origen.';
            $datos['specimen_notes'] = trim(($datos['specimen_notes'] ?? '')."\n".$nota);
            $salida[] = [
                'identificadorEstable' => $registro['id'], 'datosDwC' => $datos,
                'estadoRegistro' => $declarada && $nombreAnterior === $datos['scientific_name'] ? $declarada['estadoRegistro'] : 'Validación Manual por Curaduría',
                'motivoJustificacion' => $declarada['motivoJustificacion'] ?? null,
            ];
        }
        return $salida;
    }
}
