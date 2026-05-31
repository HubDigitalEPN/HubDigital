<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamo vencido</title>
</head>
<body style="margin:0;padding:0;background-color:#F5F7FA;font-family:Inter,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;padding:32px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.1);">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#B71C1C;padding:28px 40px;">
                            <p style="margin:0;font-size:12px;color:#FFCDD2;letter-spacing:1px;text-transform:uppercase;">Colección Entomológica EPN</p>
                            <h1 style="margin:8px 0 0;font-size:20px;color:#ffffff;font-weight:700;">⚠ Préstamo vencido</h1>
                        </td>
                    </tr>

                    {{-- Alert banner --}}
                    <tr>
                        <td style="background-color:#FFEBEE;border-left:4px solid #D32F2F;padding:14px 40px;">
                            <p style="margin:0;font-size:14px;color:#B71C1C;font-weight:600;">
                                Tu préstamo venció el {{ $fechaLimite }} — Se requiere acción inmediata
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 40px;">
                            <p style="margin:0 0 16px;font-size:15px;color:#212121;line-height:1.6;">
                                El plazo de devolución de tu préstamo de especímenes de la Colección Entomológica EPN ha <strong>expirado</strong>.
                                Es necesario que tomes acción a la brevedad posible.
                            </p>

                            <table width="100%" cellpadding="12" cellspacing="0" style="background-color:#F5F7FA;border-radius:8px;margin-bottom:24px;">
                                <tr>
                                    <td style="font-size:13px;color:#757575;">Número de préstamo</td>
                                    <td align="right" style="font-size:13px;color:#212121;font-weight:600;">{{ $prestamoId }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px;color:#757575;border-top:1px solid #E0E0E0;">Fecha límite vencida</td>
                                    <td align="right" style="font-size:13px;color:#D32F2F;font-weight:700;border-top:1px solid #E0E0E0;">{{ $fechaLimite }}</td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px;font-size:15px;color:#212121;font-weight:600;">¿Qué debes hacer ahora?</p>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:8px 0;font-size:14px;color:#212121;line-height:1.6;">
                                        <span style="display:inline-block;background-color:#D32F2F;color:#fff;border-radius:50%;width:20px;height:20px;text-align:center;font-size:12px;font-weight:700;line-height:20px;margin-right:10px;">1</span>
                                        Coordina de inmediato la devolución de los especímenes prestados.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;font-size:14px;color:#212121;line-height:1.6;">
                                        <span style="display:inline-block;background-color:#D32F2F;color:#fff;border-radius:50%;width:20px;height:20px;text-align:center;font-size:12px;font-weight:700;line-height:20px;margin-right:10px;">2</span>
                                        Contacta al curador responsable de la colección para acordar la entrega.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;font-size:14px;color:#212121;line-height:1.6;">
                                        <span style="display:inline-block;background-color:#D32F2F;color:#fff;border-radius:50%;width:20px;height:20px;text-align:center;font-size:12px;font-weight:700;line-height:20px;margin-right:10px;">3</span>
                                        Si ya realizaste la devolución, ignora este mensaje.
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;margin-bottom:20px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ route('prestamos.investigador.prestamo.detalle', $prestamoId) }}"
                                           style="display:inline-block;background-color:#D32F2F;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:8px;font-size:14px;font-weight:600;">
                                            Ver mi préstamo
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="12" cellspacing="0" style="background-color:#FFEBEE;border-radius:8px;">
                                <tr>
                                    <td style="font-size:13px;color:#B71C1C;">
                                        <strong>Contacto curador:</strong> {{ config('prestamos.curador_email', 'curador@epn.edu.ec') }}
                                    </td>
                                </tr>
                            </table>
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
