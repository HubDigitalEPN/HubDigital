<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ count($correcciones) === 1 ? 'Curaduría ajustó un dato de tu matriz' : 'Curaduría ajustó '.count($correcciones).' datos de tu matriz' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F5F7FA;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;padding:40px 16px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

    <tr>
        <td style="background-color:#1B365D;padding:32px 40px 28px;">
            <p style="margin:0 0 4px;font-size:11px;color:#7B9CC4;letter-spacing:1.5px;text-transform:uppercase;font-weight:600;">
                Colección Entomológica · EPN
            </p>
            <h1 style="margin:0;font-size:22px;color:#ffffff;font-weight:700;line-height:1.3;">
                {{ count($correcciones) === 1 ? 'Curaduría ajustó un dato de tu matriz' : 'Curaduría ajustó '.count($correcciones).' datos de tu matriz' }}
            </h1>
            @if($numero)
                <p style="margin:6px 0 0;font-size:13px;color:#A8C3E0;">N.º {{ $numero }}</p>
            @endif
        </td>
    </tr>

    <tr>
        <td style="background-color:#E1F5FE;padding:14px 40px;border-left:4px solid #0288D1;">
            <p style="margin:0;font-size:14px;color:#01579B;font-weight:600;">
                No necesitas hacer nada: tu solicitud sigue su curso
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:28px 40px 8px;">
            <p style="margin:0 0 16px;font-size:15px;color:#212121;line-height:1.6;">
                Al revisar tu matriz de especímenes, curaduría encontró
                {{ count($correcciones) === 1 ? 'un dato con un formato' : count($correcciones).' datos con formatos' }}
                que no cumplían el estándar y
                {{ count($correcciones) === 1 ? 'lo corrigió' : 'los corrigió' }}
                para no tener que devolverte la solicitud.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FA;border-radius:8px;margin:0 0 16px;">
                @foreach($correcciones as $correccion)
                    <tr>
                        <td style="padding:16px 20px;{{ ! $loop->last ? 'border-bottom:1px solid #E0E0E0;' : '' }}">
                            <p style="margin:0 0 8px;font-size:12px;color:#616161;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">
                                {{ $correccion['campo'] }}
                                <span style="text-transform:none;letter-spacing:0;font-style:italic;font-weight:400;">
                                    · {{ $correccion['especie'] }}
                                </span>
                            </p>
                            <p style="margin:0;font-size:15px;color:#212121;">
                                <span style="text-decoration:line-through;color:#9E9E9E;">{{ $correccion['anterior'] ?? '—' }}</span>
                                <span style="color:#616161;">&nbsp;→&nbsp;</span>
                                <span style="color:#2E7D32;font-weight:600;">{{ $correccion['nuevo'] ?? '—' }}</span>
                            </p>
                        </td>
                    </tr>
                @endforeach
            </table>

            <p style="margin:0 0 24px;font-size:14px;color:#616161;line-height:1.6;">
                La identificación taxonómica de tus especímenes <strong style="color:#212121;">no se modificó</strong>:
                curaduría solo ajusta formatos. Puedes ver el detalle completo, con la fecha y el responsable
                de cada cambio, en tu solicitud.
            </p>
        </td>
    </tr>

    <tr>
        <td align="center" style="padding:0 40px 32px;">
            <a href="{{ $url }}"
               style="display:inline-block;background-color:#1B365D;color:#ffffff;text-decoration:none;padding:13px 32px;border-radius:8px;font-size:15px;font-weight:600;">
                Ver mi solicitud
            </a>
        </td>
    </tr>

    <tr>
        <td style="background-color:#F5F7FA;padding:20px 40px;border-top:1px solid #E0E0E0;">
            <p style="margin:0;font-size:12px;color:#9E9E9E;line-height:1.5;">
                Museo de Colecciones Biológicas · Escuela Politécnica Nacional<br>
                Este es un mensaje automático, no respondas a este correo.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
