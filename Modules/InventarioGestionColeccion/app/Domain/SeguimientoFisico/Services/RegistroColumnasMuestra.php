<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

/**
 * Catálogo de columnas para la bandeja de muestras de colecta.
 *
 * Mismas prioridades que RegistroColumnasEspecimen:
 *  - critica: requerida para que la muestra sea válida (código + colector + fecha).
 *  - recomendada: alta calidad de muestra (localidad, protocolo).
 *  - opcional: descriptivo.
 */
final class RegistroColumnasMuestra
{
    public const PRIORIDAD_CRITICA = 'critica';

    public const PRIORIDAD_RECOMENDADA = 'recomendada';

    public const PRIORIDAD_OPCIONAL = 'opcional';

    /**
     * @return list<array{clave:string, etiqueta:string, grupo:string, prioridad:string, visiblePorDefecto:bool}>
     */
    public static function todas(): array
    {
        return [
            self::col('codigoMuestra', 'Código (oldCode)', 'identificacion', self::PRIORIDAD_CRITICA, true),
            self::col('conteoEspecimenes', '# Especímenes', 'identificacion', self::PRIORIDAD_RECOMENDADA, true),
            self::col('fechaVerbatim', 'Fecha verbatim', 'fecha', self::PRIORIDAD_CRITICA, true),
            self::col('localidadVerbatim', 'Localidad verbatim', 'localidad', self::PRIORIDAD_RECOMENDADA, true),
            self::col('colector', 'Colector', 'registro', self::PRIORIDAD_CRITICA, true),
            self::col('samplingProtocol', 'Protocolo', 'registro', self::PRIORIDAD_OPCIONAL, false),
            self::col('estadoRevision', 'Estado', 'revision', self::PRIORIDAD_CRITICA, true),
            self::col('motivoRevision', 'Motivo revisión', 'revision', self::PRIORIDAD_RECOMENDADA, true),
        ];
    }

    /** @return string[] */
    public static function clavesVisiblesPorDefecto(): array
    {
        return array_values(array_map(
            fn ($c) => $c['clave'],
            array_filter(self::todas(), fn ($c) => $c['visiblePorDefecto'])
        ));
    }

    /** @return array{clave:string, etiqueta:string, grupo:string, prioridad:string, visiblePorDefecto:bool} */
    private static function col(string $clave, string $etiqueta, string $grupo, string $prioridad, bool $visiblePorDefecto): array
    {
        return compact('clave', 'etiqueta', 'grupo', 'prioridad', 'visiblePorDefecto');
    }
}
