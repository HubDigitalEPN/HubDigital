<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recepción finalizada</title>
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
                Recepción finalizada
            </h1>
            @if($numero)
                <p style="margin:6px 0 0;font-size:13px;color:#A8C3E0;">N.º {{ $numero }}</p>
            @endif
        </td>
    </tr>

    <tr>
        <td style="background-color:#E8F5E9;padding:14px 40px;border-left:4px solid #2E7D32;">
            <p style="margin:0;font-size:14px;color:#1B5E20;font-weight:600;">
                ✓ La entrega física de tu lote fue recibida conforme
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:28px 40px;">
            <p style="margin:0 0 16px;font-size:15px;color:#212121;line-height:1.6;">
                El curador verificó tu lote y los especímenes ingresaron a la colección. Se emitió el
                Acta Digital de Recepción, disponible desde tu solicitud.
            </p>
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;border-radius:8px;margin:0 0 24px;">
                <tr><td style="padding:16px 20px;text-align:center;">
                    <p style="margin:0 0 4px;font-size:11px;color:#757575;text-transform:uppercase;letter-spacing:1px;">Estado en colección</p>
                    <p style="margin:0;font-size:20px;color:#1B365D;font-weight:700;">{{ $estadoColeccion }}</p>
                </td></tr>
            </table>
            <table cellpadding="0" cellspacing="0" style="margin:0 auto;">
                <tr><td style="border-radius:8px;background-color:#1B365D;">
                    <a href="{{ $url }}" style="display:inline-block;padding:12px 28px;font-size:14px;color:#ffffff;font-weight:600;text-decoration:none;">
                        Ver mi solicitud y el acta
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
