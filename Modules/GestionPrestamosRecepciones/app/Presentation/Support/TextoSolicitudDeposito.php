<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use App\Support\TextoOficio;

final class TextoSolicitudDeposito
{
    public static function redactar(object $solicitud): string
    {
        if (filled($solicitud->solicitud_texto ?? '')) return TextoOficio::oracion($solicitud->solicitud_texto);
        $tipo = match ($solicitud->solicitud_tipo_identificacion ?? 'cedula') {
            'ruc' => 'RUC', 'pasaporte' => 'pasaporte', default => 'cédula de identidad',
        };

        return 'Yo, '.TextoOficio::nombre($solicitud->solicitud_nombre_permiso ?: 'nombre del titular')
            .', con número de '.$tipo.' '.($solicitud->solicitud_cedula ?: '##########')
            .', en mi calidad de '.mb_convert_case(trim($solicitud->solicitud_cargo ?: 'cargo'), MB_CASE_TITLE, 'UTF-8')
            .', solicito autorice la recepción de los especímenes de '.mb_strtolower($solicitud->solicitud_grupo ?: 'grupo de especímenes')
            .', que fueron recolectados en el proyecto «'.TextoOficio::oracion($solicitud->solicitud_proyecto ?: 'título del proyecto')
            .'», cuyos detalles indico en la tabla compartida vía Google Drive, conforme a los requisitos establecidos en la página web del Laboratorio.';
    }
}
