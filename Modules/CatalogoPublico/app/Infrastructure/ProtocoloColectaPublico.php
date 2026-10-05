<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

/** Conserva la técnica fuente; agregado y filtro comparten una clave sin distinción de mayúsculas. */
final class ProtocoloColectaPublico
{
    public static function etiqueta(string $protocolo): string
    {
        return match (self::clave($protocolo)) {
            'beating' => 'Sacudida de vegetación', 'pitfall' => 'Trampa de caída',
            'hand' => 'Recolección manual', 'hand_net' => 'Red manual', 'fogging' => 'Nebulización de dosel',
            'interception_flight_fit' => 'Trampa de interceptación de vuelo', 'malaise' => 'Trampa Malaise',
            'light', 'light_trap' => 'Trampa de luz', 'berlese' => 'Embudo Berlese',
            'winkler' => 'Extractor Winkler', 'sweeping', 'sweep_net' => 'Barrido con red',
            'faeces' => 'Recolección en heces', 'bait' => 'Cebo',
            'aerial_net' => 'Red aérea', 'bait_rotten_fruit' => 'Cebo de fruta en descomposición',
            'bait_scented' => 'Cebo aromático', 'berlese_funnel' => 'Embudo Berlese', 'd_net' => 'Red en D',
            'interception_net' => 'Red de interceptación', 'lab_grown' => 'Cría en laboratorio',
            'leaf_litter' => 'Recolección de hojarasca', 'leaf_litter_sifter' => 'Tamizado de hojarasca',
            'mcphail' => 'Trampa McPhail', 'pan-trap_orange' => 'Trampa de bandeja naranja',
            'pan_trap_blue' => 'Trampa de bandeja azul', 'pitfall_carrion' => 'Trampa de caída con carroña',
            'pitfall_human_faeces' => 'Trampa de caída con heces humanas', 'pitfall_jam' => 'Trampa de caída con mermelada',
            'pitfall_pig_faeces' => 'Trampa de caída con heces porcinas', 'pitfall_underground' => 'Trampa de caída subterránea',
            'surber' => 'Muestreador Surber', 'van_someren_rydon' => 'Trampa Van Someren-Rydon',
            default => $protocolo,
        };
    }

    public static function sql(string $especimen = 'te', string $muestra = 'mc'): string
    {
        return "COALESCE(NULLIF(btrim({$especimen}.sampling_protocol), ''), NULLIF(btrim({$muestra}.sampling_protocol), ''))";
    }

    public static function claveSql(string $especimen = 'te', string $muestra = 'mc'): string
    {
        return 'LOWER('.self::sql($especimen, $muestra).')';
    }

    public static function clave(string $protocolo): string
    {
        return mb_strtolower(trim($protocolo), 'UTF-8');
    }
}
