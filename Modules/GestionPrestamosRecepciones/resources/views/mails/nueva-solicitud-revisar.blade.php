<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva solicitud por revisar</title>
</head>
<body style="margin:0;padding:0;background-color:#F5F7FA;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;padding:40px 16px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

    <tr>
        <td style="background-color:#1B365D;padding:32px 40px 28px;">
            <p style="margin:0 0 4px;font-size:11px;color:#7B9CC4;letter-spacing:1.5px;text-transform:uppercase;font-weight:600;">
                Laboratorio de Invertebrados · EPN
            </p>
            <h1 style="margin:0;font-size:22px;color:#ffffff;font-weight:700;line-height:1.3;">
                {{ ($esReenvio ?? false) ? 'Solicitud corregida reenviada' : 'Nueva solicitud por revisar' }}
            </h1>
            @if($numero)
                <p style="margin:6px 0 0;font-size:13px;color:#A8C3E0;">N.º {{ $numero }}</p>
            @endif
        </td>
    </tr>

    <tr>
        <td style="background-color:#E3F2FD;padding:14px 40px;border-left:4px solid #1976D2;">
            <p style="margin:0;font-size:14px;color:#0D47A1;font-weight:600;">
                @if($esReenvio ?? false)
                    Una solicitud que habías devuelto fue corregida y volvió a la cola de revisión
                @else
                    Una solicitud de {{ $tipoTramite ? mb_strtolower($tipoTramite) : 'depósito' }} entró a la cola de revisión documental
                @endif
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:28px 40px;">
            @if(!empty($nombreCurador))
                <p style="margin:0 0 16px;font-size:15px;color:#212121;">Hola, {{ $nombreCurador }}:</p>
            @endif
            <p style="margin:0 0 22px;font-size:15px;color:#212121;line-height:1.6;">
                @if($esReenvio ?? false)
                    <strong>{{ $nombreInvestigador ?? 'Un investigador' }}</strong> atendió tus observaciones y
                    <strong>reenvió</strong> la solicitud de {{ $tipoTramite ? mb_strtolower($tipoTramite) : 'depósito' }}.
                    Vuelve a revisar la documentación y la matriz de especímenes para aprobarla o devolverla de nuevo.
                @else
                    <strong>{{ $nombreInvestigador ?? 'Un investigador' }}</strong> completó la carga y envió una solicitud de
                    {{ $tipoTramite ? mb_strtolower($tipoTramite) : 'depósito' }}. Revisa la documentación y la matriz
                    de especímenes para aprobarla o devolverla con observaciones.
                @endif
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:1px solid #E0E0E0;border-radius:8px;border-collapse:separate;overflow:hidden;">
                <tr>
                    <td style="padding:10px 16px;font-size:13px;color:#757575;background-color:#F5F7FA;width:42%;">Investigador</td>
                    <td style="padding:10px 16px;font-size:13px;color:#212121;font-weight:600;">{{ $nombreInvestigador ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 16px;font-size:13px;color:#757575;background-color:#F5F7FA;border-top:1px solid #E0E0E0;">Tipo de trámite</td>
                    <td style="padding:10px 16px;font-size:13px;color:#212121;border-top:1px solid #E0E0E0;">{{ $tipoTramite ?? 'Depósito' }}</td>
                </tr>
                @if($numero)
                    <tr>
                        <td style="padding:10px 16px;font-size:13px;color:#757575;background-color:#F5F7FA;border-top:1px solid #E0E0E0;">N.º solicitud</td>
                        <td style="padding:10px 16px;font-size:13px;color:#212121;border-top:1px solid #E0E0E0;font-family:monospace;">{{ $numero }}</td>
                    </tr>
                @endif
                @if(!empty($fechaEnvio))
                    <tr>
                        <td style="padding:10px 16px;font-size:13px;color:#757575;background-color:#F5F7FA;border-top:1px solid #E0E0E0;">Fecha de envío</td>
                        <td style="padding:10px 16px;font-size:13px;color:#212121;border-top:1px solid #E0E0E0;">{{ $fechaEnvio }}</td>
                    </tr>
                @endif
            </table>

            <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
                <tr><td style="border-radius:8px;background-color:#1B365D;">
                    <a href="{{ $url }}" style="display:inline-block;padding:12px 28px;font-size:14px;color:#ffffff;font-weight:600;text-decoration:none;">
                        Revisar la solicitud
                    </a>
                </td></tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:20px 40px;border-top:1px solid #E0E0E0;">
            <p style="margin:0;font-size:12px;color:#757575;line-height:1.5;">
                Este es un mensaje automático del sistema del Laboratorio de Invertebrados de la EPN.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
