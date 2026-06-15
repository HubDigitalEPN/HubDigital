<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamo {{ $numeroPrestamo }} cerrado</title>
</head>
<body style="margin:0;padding:0;background-color:#F5F7FA;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;padding:40px 16px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

    {{-- Header --}}
    <tr>
        <td style="background-color:#1B365D;padding:32px 40px 28px;">
            <p style="margin:0 0 4px;font-size:11px;color:#7B9CC4;letter-spacing:1.5px;text-transform:uppercase;font-weight:600;">
                Colección Entomológica · EPN
            </p>
            <h1 style="margin:0;font-size:22px;color:#ffffff;font-weight:700;line-height:1.3;">
                Préstamo cerrado
            </h1>
            <p style="margin:6px 0 0;font-size:13px;color:#A8C3E0;">
                N.º {{ $numeroPrestamo }}
            </p>
        </td>
    </tr>

    {{-- Result banner --}}
    @if(! $esConObservacion)
    <tr>
        <td style="background-color:#E8F5E9;padding:14px 40px;border-left:4px solid #2E7D32;">
            <p style="margin:0;font-size:14px;color:#1B5E20;font-weight:600;">
                ✓ Devolución verificada sin novedades — especímenes en condición apta
            </p>
        </td>
    </tr>
    @else
    <tr>
        <td style="background-color:#FFF3E0;padding:14px 40px;border-left:4px solid #FF9800;">
            <p style="margin:0;font-size:14px;color:#BF360C;font-weight:600;">
                ⚠ Devolución verificada con observación — especímenes en condición no apta
            </p>
        </td>
    </tr>
    @endif

    {{-- Body --}}
    <tr>
        <td style="padding:32px 40px 24px;">
            <p style="margin:0 0 20px;font-size:15px;color:#212121;line-height:1.7;">
                Hola, <strong>{{ $investigadorNombre }}</strong>.<br>
                El curador ha completado la verificación de tu devolución. A continuación el resumen:
            </p>

            {{-- Info table --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E0E0E0;border-radius:8px;overflow:hidden;margin-bottom:24px;">
                <tr style="background-color:#F5F7FA;">
                    <td style="padding:12px 16px;font-size:12px;color:#757575;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;width:50%;">
                        N.º de préstamo
                    </td>
                    <td style="padding:12px 16px;font-size:14px;color:#1B365D;font-weight:700;font-family:monospace;">
                        {{ $numeroPrestamo }}
                    </td>
                </tr>
                <tr style="border-top:1px solid #E0E0E0;">
                    <td style="padding:12px 16px;font-size:12px;color:#757575;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">
                        Resultado
                    </td>
                    <td style="padding:12px 16px;font-size:14px;color:#212121;font-weight:600;">
                        {{ $resultado }}
                    </td>
                </tr>
                <tr style="border-top:1px solid #E0E0E0;">
                    <td style="padding:12px 16px;font-size:12px;color:#757575;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">
                        Condición de especímenes
                    </td>
                    <td style="padding:12px 16px;font-size:14px;font-weight:600;color:{{ $esConObservacion ? '#BF360C' : '#2E7D32' }};">
                        {{ $condicion }}
                    </td>
                </tr>
            </table>

            {{-- Observacion block --}}
            @if($observacion)
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFF8F0;border:1px solid #FFD0A0;border-radius:8px;margin-bottom:24px;">
                <tr>
                    <td style="padding:16px 20px;">
                        <p style="margin:0 0 6px;font-size:12px;color:#BF360C;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                            Observaciones del curador
                        </p>
                        <p style="margin:0;font-size:14px;color:#4E342E;line-height:1.6;">
                            {{ $observacion }}
                        </p>
                    </td>
                </tr>
            </table>
            @endif

            {{-- CTA --}}
            <table cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td style="background-color:#1B365D;border-radius:6px;">
                        <a href="{{ $prestamoUrl }}"
                           style="display:inline-block;padding:12px 28px;font-size:14px;color:#ffffff;font-weight:600;text-decoration:none;">
                            Ver préstamo en el sistema →
                        </a>
                    </td>
                </tr>
            </table>

            <p style="margin:0;font-size:13px;color:#9E9E9E;line-height:1.6;">
                Si tienes dudas sobre el resultado, comunícate con el curador responsable de la colección.
            </p>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="background-color:#F5F7FA;padding:18px 40px;border-top:1px solid #E0E0E0;">
            <p style="margin:0;font-size:11px;color:#9E9E9E;line-height:1.6;">
                Colección Entomológica — Escuela Politécnica Nacional<br>
                Este es un mensaje automático, por favor no respondas a este correo.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
