<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 70px 68px 65px; }
        body { color: #182536; font: 11px DejaVu Sans, sans-serif; line-height: 1.7; }
        .oficio { text-align: right; margin-bottom: 26px; }
        .destinatario { margin-bottom: 30px; line-height: 1.45; }
        .cuerpo { text-align: justify; margin: 16px 0 30px; white-space: pre-line; overflow-wrap: break-word; }
        .firma { margin-top: 28px; position: relative; height: 116px; }
        .firma a { display: block; position: absolute; color: #fff; font-size: 1px; line-height: 1px; text-decoration: none; }
        .firma .bloque { inset: 0; }
        .firma .zona { left: 8px; right: 8px; top: 8px; bottom: 8px; }
        .firmante { border-top: 1px solid #334155; padding-top: 8px; }
        .meta { color: #64748b; font-size: 8px; margin-top: 42px; }
    </style>
</head>
<body>
    <div class="oficio">
        @if($solicitud->solicitud_oficio)
            <div>No. oficio: {{ $solicitud->solicitud_oficio }}</div>
        @endif
        <div>Quito, {{ $solicitud->created_at?->copy()->timezone('America/Guayaquil')->locale('es')->translatedFormat('j \\d\\e F \\d\\e Y') }}</div>
    </div>

    <div class="destinatario">
        <strong>Dr. Adrian Troya</strong><br>
        Jefe<br>
        Laboratorio de Invertebrados<br>
        Departamento de Biología<br>
        Escuela Politécnica Nacional
    </div>

    <p>De mis consideraciones,</p>

    <p class="cuerpo">
        {{ \Modules\GestionPrestamosRecepciones\Presentation\Support\TextoSolicitudDeposito::redactar($solicitud) }}
    </p>

    <p>Sin otro particular, me suscribo de usted.</p>
    <p>Atentamente,</p>

    <div class="firma">
        <a class="bloque" href="{{ $perfilFirma['bloque'] }}">HUBDIGITAL BLOQUE NOMINAL {{ $perfilFirma['rol'] }}</a>
        <a class="zona" href="{{ $perfilFirma['zona'] }}">HUBDIGITAL ZONA FIRMA {{ $perfilFirma['rol'] }}</a>
    </div>
    <div class="firmante">
        <strong>{{ \App\Support\TextoOficio::nombre($solicitud->solicitud_nombre_permiso) }}</strong><br>
        {{ \App\Support\TextoOficio::nombre($solicitud->solicitud_institucion) }}<br>
        {{ mb_strtolower($solicitud->solicitud_correo) }}
    </div>

    <div class="meta">Solicitud {{ $solicitud->numero }} · versión {{ $solicitud->solicitud_documento_version ?? 1 }}. El ejemplar oficial es el PDF con firma electrónica validada.</div>
</body>
</html>
