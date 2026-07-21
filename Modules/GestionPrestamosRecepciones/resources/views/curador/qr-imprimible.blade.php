<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Código QR de lote {{ $codigo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; color: #212121; padding: 40px; background: #F5F7FA; }
        .hoja { max-width: 520px; margin: 0 auto; }
        .acciones { text-align: center; margin-bottom: 20px; }
        .btn {
            display: inline-block; background: #1B365D; color: #fff; text-decoration: none;
            font-size: 14px; font-weight: 600; padding: 10px 20px; border: 0; border-radius: 8px; cursor: pointer;
        }
        .marco { background: #fff; border: 2px solid #1B365D; border-radius: 12px; padding: 32px; text-align: center; }
        .titulo { color: #1B365D; font-size: 20px; font-weight: bold; margin-bottom: 4px; }
        .subtitulo { color: #757575; font-size: 12px; margin-bottom: 24px; }
        .qr { width: 260px; height: 260px; margin: 0 auto 16px; }
        .qr img { width: 260px; height: 260px; }
        .codigo {
            font-family: DejaVu Sans Mono, monospace; font-size: 24px; font-weight: bold;
            letter-spacing: 2px; color: #1B365D; margin-bottom: 28px;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { padding: 8px 12px; font-size: 12px; text-align: left; border-bottom: 1px solid #E0E0E0; }
        td.etq { color: #757575; width: 40%; }
        td.val { color: #212121; font-weight: bold; }
        .nota {
            margin-top: 24px; font-size: 11px; color: #757575; line-height: 1.5;
            background: #F5F7FA; border-radius: 8px; padding: 12px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .acciones { display: none; }
            .marco { border-color: #1B365D; }
        }
    </style>
</head>
<body>
    <div class="hoja">
        <div class="acciones">
            <button type="button" class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button>
        </div>
        <div class="marco">
        <div class="titulo">Código QR de lote</div>
        <div class="subtitulo">Identificación del lote de muestras para la entrega física</div>

        <div class="qr">
            <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="Código QR {{ $codigo }}">
        </div>

        <div class="codigo">{{ $codigo }}</div>

        <table>
            <tr>
                <td class="etq">N.º de solicitud</td>
                <td class="val">{{ $numero ?? '—' }}</td>
            </tr>
            <tr>
                <td class="etq">Tipo de trámite</td>
                <td class="val">{{ $tipoTramite }}</td>
            </tr>
            <tr>
                <td class="etq">Depositante</td>
                <td class="val">{{ $investigador }}</td>
            </tr>
            <tr>
                <td class="etq">Fecha de aprobación</td>
                <td class="val">{{ $fechaAprobacion ?? '—' }}</td>
            </tr>
        </table>

        <div class="nota">
            Adjunte este código a la entrega física de las muestras. El personal de recepción
            lo escaneará para identificar el lote y registrar el ingreso a la colección.
        </div>
        </div>
    </div>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 300);
        });
    </script>
</body>
</html>
