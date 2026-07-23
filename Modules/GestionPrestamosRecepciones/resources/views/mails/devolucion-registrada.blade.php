<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamo {{ $numeroPrestamo }} — devolución registrada</title>
</head>
<body style="margin:0;padding:0;background-color:#F5F7FA;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;padding:40px 16px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

    {{-- Header --}}
    <tr>
        <td style="background-color:#1B365D;padding:32px 40px 28px;">
            <p style="margin:0 0 4px;font-size:11px;color:#7B9CC4;letter-spacing:1.5px;text-transform:uppercase;font-weight:600;">
                Laboratorio de Invertebrados · EPN
            </p>
            <h1 style="margin:0;font-size:22px;color:#ffffff;font-weight:700;line-height:1.3;">
                Devolución registrada
            </h1>
            <p style="margin:6px 0 0;font-size:13px;color:#A8C3E0;">
                N.º {{ $numeroPrestamo }}
            </p>
        </td>
    </tr>

    {{-- Status banner --}}
    <tr>
        <td style="background-color:#E3F2FD;padding:14px 40px;border-left:4px solid #1976D2;">
            <p style="margin:0;font-size:14px;color:#0D47A1;font-weight:600;">
                ✓ Registramos el envío de tu devolución — el préstamo pasó a revisión
            </p>
        </td>
    </tr>

    {{-- Body --}}
    <tr>
        <td style="padding:32px 40px 24px;">
            <p style="margin:0 0 20px;font-size:15px;color:#212121;line-height:1.7;">
                Hola, <strong>{{ $investigadorNombre }}</strong>.<br>
                Hemos registrado la devolución de los especímenes del préstamo
                <strong>{{ $numeroPrestamo }}</strong>.
            </p>

            {{-- Next step --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;border:1px solid #E0E0E0;border-radius:8px;margin-bottom:24px;">
                <tr>
                    <td style="padding:16px 20px;">
                        <p style="margin:0 0 6px;font-size:12px;color:#1976D2;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                            Siguiente paso
                        </p>
                        <p style="margin:0;font-size:14px;color:#212121;line-height:1.6;">
                            El curador verificará y cerrará el préstamo. Te notificaremos por
                            este medio cuando la verificación se complete.
                        </p>
                    </td>
                </tr>
            </table>

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
                Si tienes dudas sobre el estado de tu devolución, comunícate con el curador responsable de la colección.
            </p>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="background-color:#F5F7FA;padding:18px 40px;border-top:1px solid #E0E0E0;">
            <p style="margin:0;font-size:11px;color:#9E9E9E;line-height:1.6;">
                Laboratorio de Invertebrados — Escuela Politécnica Nacional<br>
                Este es un mensaje automático, por favor no respondas a este correo.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
