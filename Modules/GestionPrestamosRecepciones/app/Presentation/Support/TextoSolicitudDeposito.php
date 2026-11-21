<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use App\Support\TextoOficio;

final class TextoSolicitudDeposito
{
    public const PLANTILLA = 'solicitud.texto_pdf';

    private const MARCADORES = ['titular', 'tipo_identificacion', 'identificacion', 'cargo', 'grupo', 'proyecto'];

    public static function marcadoresValidos(string $texto): bool
    {
        preg_match_all('/\{([^{}]+)\}/u', $texto, $marcadores);
        $encontrados = array_unique($marcadores[1]);

        return array_diff(self::MARCADORES, $encontrados) === [] && array_diff($encontrados, self::MARCADORES) === [];
    }

    public static function redactar(object $solicitud, ?string $plantilla = null): string
    {
        if (filled($solicitud->solicitud_texto ?? '')) return TextoOficio::oracion($solicitud->solicitud_texto);
        $tipo = match ($solicitud->solicitud_tipo_identificacion ?? 'cedula') {
            'ruc' => 'RUC', 'pasaporte' => 'pasaporte', default => 'cédula de identidad',
        };

        return strtr($plantilla ?? config('wizard-copy.'.self::PLANTILLA), [
            '{titular}' => TextoOficio::nombre($solicitud->solicitud_nombre_permiso ?: 'nombre del titular'),
            '{tipo_identificacion}' => $tipo,
            '{identificacion}' => $solicitud->solicitud_cedula ?: '##########',
            '{cargo}' => mb_convert_case(trim($solicitud->solicitud_cargo ?: 'cargo'), MB_CASE_TITLE, 'UTF-8'),
            '{grupo}' => mb_strtolower($solicitud->solicitud_grupo ?: 'grupo de especímenes'),
            '{proyecto}' => TextoOficio::oracion($solicitud->solicitud_proyecto ?: 'título del proyecto'),
        ]);
    }
}
