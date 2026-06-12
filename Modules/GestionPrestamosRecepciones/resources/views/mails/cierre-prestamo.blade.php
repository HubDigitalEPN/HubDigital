<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamo cerrado</title>
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
                            <h1 style="margin:8px 0 0;font-size:20px;color:#ffffff;font-weight:700;">Préstamo cerrado</h1>
                        </td>
                    </tr>

                    {{-- Result banner --}}
                    @if($condicion === 'apto')
                    <tr>
                        <td style="background-color:#E8F5E9;border-left:4px solid #4CAF50;padding:14px 40px;">
                            <p style="margin:0;font-size:14px;color:#2E7D32;font-weight:600;">
                                Devolución verificada sin novedades — especímenes en condición apta
                            </p>
                        </td>
                    </tr>
                    @else
                    <tr>
                        <td style="background-color:#FFF3E0;border-left:4px solid #FF9800;padding:14px 40px;">
                            <p style="margin:0;font-size:14px;color:#E65100;font-weight:600;">
                                Devolución verificada con observación — especímenes en condición no apta
                            </p>
                        </td>
                    </tr>
                    @endif

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 40px;">
                            <p style="margin:0 0 16px;font-size:15px;color:#212121;line-height:1.6;">
                                El curador ha completado la verificación de tu devolución. A continuación el resumen del resultado:
                            </p>

                            <table width="100%" cellpadding="12" cellspacing="0" style="background-color:#F5F7FA;border-radius:8px;margin-bottom:24px;">
                                <tr>
                                    <td style="font-size:13px;color:#757575;">Número de préstamo</td>
                                    <td align="right" style="font-size:13px;color:#212121;font-weight:600;">{{ $prestamoId }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px;color:#757575;border-top:1px solid #E0E0E0;">Resultado de verificación</td>
                                    <td align="right" style="font-size:13px;color:#212121;font-weight:600;border-top:1px solid #E0E0E0;">{{ $resultado }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px;color:#757575;border-top:1px solid #E0E0E0;">Condición de especímenes</td>
                                    <td align="right" style="font-size:13px;color:#212121;font-weight:600;border-top:1px solid #E0E0E0;">
                                        {{ $condicion === 'apto' ? 'Apto' : 'No apto' }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0;font-size:14px;color:#757575;line-height:1.6;">
                                Si tienes dudas sobre el resultado, comunícate con el curador responsable de la colección.
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
