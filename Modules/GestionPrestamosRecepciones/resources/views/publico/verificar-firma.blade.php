<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verificación de firma · Hub Digital</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px 16px; background: #f4f6f9; color: #182536; font: 16px/1.5 system-ui, sans-serif; }
        main { max-width: 480px; margin: 32px auto; padding: 28px 24px; border: 1px solid #dce2ea; border-radius: 12px; background: #fff; }
        .marca { margin: 0 0 24px; color: #174a9a; font-weight: 700; }
        h1 { margin: 12px 0; font-size: 24px; line-height: 1.25; }
        .estado { display: inline-block; padding: 4px 12px; border-radius: 20px; font-weight: 600; background: #fff1cc; color: #745100; }
        .valido { background: #e8f7ea; color: #17751c; }
        .invalido { background: #fff0f0; color: #a42c2c; }
        dl { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e9ef; }
        dt { font-size: 13px; color: #5e6b7c; }
        dd { margin: 2px 0 12px; overflow-wrap: anywhere; }
        .nota { margin-top: 24px; font-size: 13px; color: #5e6b7c; }
    </style>
</head>
<body>
<main>
    <p class="marca">Hub Digital · Museo de Historia Natural</p>
    <span class="estado {{ $estado }}">{{ match($estado) { 'valido' => 'Firma validada', 'pendiente' => 'Guardado pendiente', 'no_disponible' => 'Verificación no disponible', default => 'Documento no verificable' } }}</span>
    <h1>Verificación de firma electrónica</h1>
    <p role="status">{{ $mensaje }}</p>
    @if($numero)
        <dl><dt>Documento</dt><dd>{{ $numero }} · versión {{ $version }}</dd><dt>Fecha de firma</dt><dd>{{ $fecha }} (Ecuador)</dd></dl>
    @endif

</main>
</body>
</html>
