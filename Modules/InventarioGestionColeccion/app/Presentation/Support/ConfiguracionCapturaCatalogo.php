<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Support;

use Illuminate\Support\Facades\DB;

final class ConfiguracionCapturaCatalogo
{
    private const CAMPOS_AUTOMATICOS_U_OMITIDOS = ['codigoCatalogo', 'occurrenceId', 'catalogNumber', 'cardexLiquidCollectionCode'];

    public const OBLIGATORIOS_FIJOS = ['taxonId', 'fechaColecta'];

    private ?array $guardados = null;

    public function campos(): array
    {
        if ($this->guardados === null) {
            $json = DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'captura-manual')->value('definicion');
            $this->guardados = $json === null ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }
        $campos = [];
        foreach (AyudasCapturaCatalogo::campos() as $clave => [$titulo]) {
            if (in_array($clave, self::CAMPOS_AUTOMATICOS_U_OMITIDOS, true)) continue;
            $campos[$clave] = [
                'etiqueta' => $this->guardados[$clave]['etiqueta'] ?? $titulo,
                'obligatorio' => in_array($clave, self::OBLIGATORIOS_FIJOS, true)
                    || ($this->guardados[$clave]['obligatorio'] ?? $clave === 'colector'),
            ];
        }

        return $campos;
    }

    public function guardar(array $campos, string $usuario): void
    {
        $definicion = [];
        foreach ($this->campos() as $clave => $anterior) {
            $entrada = $campos[$clave] ?? $anterior;
            $definicion[$clave] = [
                'etiqueta' => trim($entrada['etiqueta']),
                'obligatorio' => in_array($clave, self::OBLIGATORIOS_FIJOS, true) || (bool) $entrada['obligatorio'],
            ];
        }
        DB::table('taxonomia.configuracion_plantilla_catalogo')->upsert([[
            'clave' => 'captura-manual', 'definicion' => json_encode($definicion, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'actualizado_por' => $usuario, 'created_at' => now(), 'updated_at' => now(),
        ]], ['clave'], ['definicion', 'actualizado_por', 'updated_at']);
        $this->guardados = $definicion;
    }
}
