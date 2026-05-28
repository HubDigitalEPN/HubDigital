<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de devolución</title>
</head>
<body style="margin:0;padding:0;background-color:#F5F7FA;font-family:Inter,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.1);">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#1B365D;padding:28px 40px;">
                            <p style="margin:0;font-size:12px;color:#90A4C4;letter-spacing:1px;text-transform:uppercase;">Colección Entomológica EPN</p>
                            <h1 style="margin:8px 0 0;font-size:20px;color:#ffffff;font-weight:700;">Recordatorio de devolución</h1>
                        </td>
                    </tr>

                    {{-- Alert banner --}}
                    <tr>
                        <td style="background-color:#FFF8E1;border-left:4px solid #FF9800;padding:14px 40px;">
                            <p style="margin:0;font-size:14px;color:#E65100;font-weight:600;">
                                Tu préstamo vence el {{ $fechaLimite }}
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 40px;">
                            <p style="margin:0 0 16px;font-size:15px;color:#212121;line-height:1.6;">
                                Te recordamos que tienes un préstamo de especímenes de la Colección Entomológica EPN próximo a vencer.
                            </p>

                            <table width="100%" cellpadding="12" cellspacing="0" style="background-color:#F5F7FA;border-radius:8px;margin-bottom:24px;">
                                <tr>
                                    <td style="font-size:13px;color:#757575;">Número de préstamo</td>
                                    <td align="right" style="font-size:13px;color:#212121;font-weight:600;">{{ $prestamoId }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px;color:#757575;border-top:1px solid #E0E0E0;">Fecha límite de devolución</td>
                                    <td align="right" style="font-size:13px;color:#E65100;font-weight:700;border-top:1px solid #E0E0E0;">{{ $fechaLimite }}</td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px;font-size:15px;color:#212121;line-height:1.6;">
                                Por favor, coordina la devolución de los especímenes antes de la fecha indicada para evitar inconvenientes.
                            </p>
                            <p style="margin:0;font-size:14px;color:#757575;line-height:1.6;">
                                Si ya coordinaste la devolución, puedes ignorar este mensaje.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#F5F7FA;padding:20px 40px;border-top:1px solid #E0E0E0;">
                            <p style="margin:0;font-size:12px;color:#757575;">
                                Colección Entomológica — Escuela Politécnica Nacional<br>
                                Este es un mensaje automático, por favor no respondas a este correo.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
