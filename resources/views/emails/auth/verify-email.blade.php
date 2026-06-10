<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <title>Verifica tu cuenta</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FA; -webkit-font-smoothing:antialiased; font-family:'Inter',Helvetica,Arial,sans-serif;">

    {{-- Preheader oculto: mejora la previsualización en la bandeja --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#F5F7FA; font-size:1px; line-height:1px;">
        Confirma tu correo para activar tu cuenta en Hub Digital.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%;">

                    {{-- Encabezado de marca --}}
                    <tr>
                        <td style="background-color:#1B365D; border-radius:12px 12px 0 0; padding:28px 32px; text-align:center;">
                            <span style="display:inline-block; font-family:'Roboto Slab',Georgia,serif; font-size:22px; font-weight:700; color:#FFFFFF; letter-spacing:0.3px;">
                                Hub Digital
                            </span>
                            <div style="margin-top:4px; font-size:12px; color:#AFC1DB; letter-spacing:0.4px;">
                                Colección Entomológica EPN
                            </div>
                        </td>
                    </tr>

                    {{-- Tarjeta principal --}}
                    <tr>
                        <td style="background-color:#FFFFFF; padding:36px 32px; border-left:1px solid #E2E8F0; border-right:1px solid #E2E8F0;">

                            <h1 style="margin:0 0 16px; font-family:'Roboto Slab',Georgia,serif; font-size:20px; font-weight:700; color:#1B365D;">
                                Confirma tu correo electrónico
                            </h1>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#1F2933;">
                                Hola{{ $user->name ? ' '.$user->name : '' }}, gracias por registrarte. Para activar tu
                                cuenta y acceder a la plataforma, confirma que esta dirección de correo te pertenece.
                            </p>

                            {{-- Botón de acción --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0;">
                                <tr>
                                    <td align="center" style="border-radius:8px; background-color:#2E7D32;">
                                        <a href="{{ $url }}" target="_blank"
                                           style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:600; color:#FFFFFF; text-decoration:none; border-radius:8px;">
                                            Verificar mi cuenta
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#64748B;">
                                Por tu seguridad, este enlace caduca en {{ $expireMinutes }} minutos. Si expira, podrás
                                solicitar uno nuevo desde la pantalla de verificación.
                            </p>

                            <p style="margin:24px 0 8px; font-size:13px; line-height:1.6; color:#64748B;">
                                Si el botón no funciona, copia y pega esta dirección en tu navegador:
                            </p>
                            <p style="margin:0; font-size:13px; line-height:1.5; word-break:break-all;">
                                <a href="{{ $url }}" target="_blank" style="color:#1976D2; text-decoration:underline;">{{ $url }}</a>
                            </p>

                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="background-color:#FFFFFF; padding:0 32px 32px; border-left:1px solid #E2E8F0; border-right:1px solid #E2E8F0; border-bottom:1px solid #E2E8F0; border-radius:0 0 12px 12px;">
                            <hr style="border:none; border-top:1px solid #E2E8F0; margin:0 0 20px;">
                            <p style="margin:0 0 4px; font-size:12px; line-height:1.6; color:#94A3B8;">
                                Si no creaste esta cuenta, puedes ignorar este mensaje con tranquilidad; no se realizará
                                ninguna acción.
                            </p>
                            <p style="margin:12px 0 0; font-size:12px; color:#94A3B8;">
                                © {{ date('Y') }} Colección Entomológica EPN · Hub Digital
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
