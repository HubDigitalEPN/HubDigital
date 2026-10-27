<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablece tu contraseña de HubDigital</title>
</head>
<body style="margin:0;padding:0;background:#F5F7FA;font-family:Helvetica,Arial,sans-serif;color:#1F2933;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                <tr><td style="background:#1B365D;color:#FFFFFF;padding:24px 28px;border-radius:12px 12px 0 0;">
                    <strong style="font-size:22px;">HubDigital</strong><br>
                    <span style="font-size:13px;">Laboratorio de Invertebrados EPN</span>
                </td></tr>
                <tr><td style="background:#FFFFFF;padding:28px;border:1px solid #E2E8F0;border-radius:0 0 12px 12px;">
                    <h1 style="margin:0 0 20px;font-size:22px;color:#1B365D;">Restablece tu contraseña</h1>
                    <p style="font-size:15px;line-height:1.6;">Recibimos una solicitud para cambiar la contraseña de tu cuenta. Abre el siguiente enlace para elegir una nueva.</p>
                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                        <tr><td style="background:#184A96;border-radius:6px;">
                            <a href="{{ $url }}" style="display:inline-block;padding:15px 24px;color:#FFFFFF;font-size:15px;font-weight:bold;text-decoration:none;">Restablecer mi contraseña</a>
                        </td></tr>
                    </table>
                    <p style="font-size:14px;line-height:1.6;">Este enlace caduca en {{ $expireMinutes }} minutos y solo puede utilizarse una vez. Si caduca, solicita otro desde «¿Olvidaste tu contraseña?» en la pantalla de inicio de sesión.</p>
                    <p style="font-size:14px;line-height:1.6;">Si el botón no funciona, copia esta dirección en tu navegador:</p>
                    <p style="font-size:13px;line-height:1.6;word-break:break-all;"><a href="{{ $url }}" style="color:#184A96;">{{ $url }}</a></p>
                    <hr style="border:0;border-top:1px solid #E2E8F0;margin:24px 0;">
                    <p style="font-size:14px;line-height:1.6;">Si no solicitaste este cambio, ignora este mensaje. Tu contraseña actual seguirá funcionando. Nunca te pediremos que nos envíes tu contraseña por correo.</p>
                    <p style="font-size:12px;line-height:1.6;">© {{ date('Y') }} Laboratorio de Invertebrados EPN · HubDigital</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
