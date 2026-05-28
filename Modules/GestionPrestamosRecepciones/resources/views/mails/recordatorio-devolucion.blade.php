@php
    $esVencido = $estadoRecordatorio === 'vencido';
    $bannerBg           = $esVencido ? '#FCEBEB' : '#FAEEDA';
    $bannerBorder       = $esVencido ? '#A32D2D' : '#BA7517';
    $bannerIconColor    = $esVencido ? '#A32D2D' : '#854F0B';
    $bannerTitleColor   = $esVencido ? '#A32D2D' : '#854F0B';
    $bannerSubtitleColor = $esVencido ? '#A32D2D' : '#BA7517';
    $bannerSubtitle     = $esVencido ? 'Su préstamo ha vencido' : 'Su préstamo está próximo a vencer';
    $badgeBg            = $bannerBg;
    $badgeColor         = $bannerTitleColor;
    $badgeText          = $esVencido ? 'Vencido' : 'Próximo a vencer';
    $parrafo            = $esVencido
        ? 'Le informamos que el plazo de devolución de su préstamo de especímenes ha vencido. Por favor, proceda a realizar la devolución a la brevedad posible.'
        : 'Le recordamos que el plazo de devolución de su préstamo de especímenes se aproxima. Por favor asegúrese de devolver los materiales antes de la fecha límite establecida.';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $esVencido ? 'Aviso: su préstamo ha vencido' : 'Recordatorio: préstamo próximo a vencer' }}</title>
</head>
<body style="margin:0;padding:0;background:#F5F7FA;font-family:Inter,Arial,sans-serif;">
    <div style="padding:32px 16px;">
        <div style="max-width:560px;margin:0 auto;background:#FFFFFF;border-radius:8px;border:1px solid #E0E0E0;overflow:hidden;">

            {{-- Header --}}
            <div style="background:#0F6E56;padding:32px 32px 24px;text-align:center;">
                <p style="margin:0 0 4px;font-size:13px;color:#9FE1CB;letter-spacing:0.05em;">COLECCIÓN ENTOMOLÓGICA</p>
                <p style="margin:0;font-size:20px;font-weight:500;color:#E1F5EE;">Escuela Politécnica Nacional</p>
            </div>

            {{-- Body --}}
            <div style="padding:32px;">

                {{-- Banner condicional --}}
                <div style="background:{{ $bannerBg }};border-left:3px solid {{ $bannerBorder }};border-radius:0 6px 6px 0;padding:12px 16px;margin-bottom:24px;">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr>
                            <td style="width:32px;vertical-align:middle;padding-right:12px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="{{ $bannerIconColor }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </td>
                            <td style="vertical-align:middle;">
                                <p style="margin:0;font-size:13px;font-weight:500;color:{{ $bannerTitleColor }};">Recordatorio de devolución</p>
                                <p style="margin:0;font-size:12px;color:{{ $bannerSubtitleColor }};">{{ $bannerSubtitle }}</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p style="font-size:15px;color:#212121;margin:0 0 16px;">Estimado/a investigador/a,</p>
                <p style="font-size:14px;color:#757575;line-height:1.7;margin:0 0 24px;">{{ $parrafo }}</p>

                {{-- Tabla de datos --}}
                <div style="background:#F5F7FA;border-radius:6px;padding:16px 20px;margin-bottom:24px;">
                    <table style="width:100%;font-size:13px;border-collapse:collapse;">
                        <tr>
                            <td style="color:#757575;padding:6px 0;border-bottom:1px solid #E0E0E0;">Número de préstamo</td>
                            <td style="text-align:right;font-weight:500;color:#212121;padding:6px 0;border-bottom:1px solid #E0E0E0;">{{ $prestamoId }}</td>
                        </tr>
                        <tr>
                            <td style="color:#757575;padding:6px 0;border-bottom:1px solid #E0E0E0;">Estado</td>
                            <td style="text-align:right;padding:6px 0;border-bottom:1px solid #E0E0E0;">
                                <span style="background:{{ $badgeBg }};color:{{ $badgeColor }};font-size:11px;padding:2px 8px;border-radius:12px;font-weight:500;">{{ $badgeText }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="color:#757575;padding:6px 0;">Fecha límite de devolución</td>
                            <td style="text-align:right;font-weight:500;color:#212121;padding:6px 0;">{{ $fechaLimite }}</td>
                        </tr>
                    </table>
                </div>

                {{-- CTA --}}
                <div style="text-align:center;margin-bottom:24px;">
                    <a href="{{ route('prestamos.investigador.mis-prestamos') }}" style="display:inline-block;background:#0F6E56;color:#E1F5EE;text-decoration:none;padding:10px 28px;border-radius:6px;font-size:14px;font-weight:500;">Ver mi préstamo</a>
                </div>

                <p style="font-size:13px;color:#757575;line-height:1.6;margin:0;">Si ya realizó la devolución o tiene alguna consulta, comuníquese con el curador responsable de la colección.</p>
            </div>

            {{-- Footer --}}
            <div style="border-top:1px solid #E0E0E0;padding:16px 32px;text-align:center;">
                <p style="margin:0;font-size:12px;color:#757575;">Este es un mensaje automático — por favor no responda a este correo</p>
                <p style="margin:4px 0 0;font-size:12px;color:#757575;">Colección Entomológica EPN · Quito, Ecuador</p>
            </div>

        </div>
    </div>
</body>
</html>
