@php
    /**
     * Plantilla del "Acta recepción-depósito" oficial (MEPN / EPN). Se renderiza con
     * DomPDF para producir el PDF que el curador firma electrónicamente (FirmaEC).
     * Los logos se incrustan en base64 porque DomPDF no resuelve URLs externas.
     */
    $logoEpn = public_path('images/logo-epn.png');
    $logoDb = public_path('images/logo-DB.jpg');
    $logoEpnB64 = is_file($logoEpn) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoEpn)) : null;
    $logoDbB64 = is_file($logoDb) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoDb)) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta recepción-depósito {{ $recepcion->numeroSolicitud }}</title>
    <style>
        @page { margin: 1.5cm 1.8cm 2.2cm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { color: #1a1a1a; font-size: 10pt; line-height: 1.35; margin: 0; }

        table.membrete { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.membrete td { vertical-align: middle; }
        table.membrete td.izq { text-align: left; width: 50%; }
        table.membrete td.der { text-align: right; width: 50%; }
        img.escudo-epn { height: 66px; }
        img.logo-db { height: 56px; }

        table.cabecera { width: 100%; border-collapse: collapse; margin-bottom: 13px; }
        table.cabecera td { font-size: 10pt; }
        table.cabecera td.der { text-align: right; }

        .destinatario { margin-bottom: 12px; }
        .destinatario .nombre { font-weight: bold; }

        p { margin: 0 0 9px; text-align: justify; }
        .saludo { margin-bottom: 9px; }

        ul.detalle { margin: 0 0 9px; padding-left: 18px; }
        ul.detalle > li { margin-bottom: 7px; }

        table.detalle-tabla { border-collapse: collapse; width: 100%; margin-top: 5px; font-size: 9.5pt; }
        table.detalle-tabla th, table.detalle-tabla td { border: 1px solid #999; padding: 5px 9px; text-align: center; }
        table.detalle-tabla th { font-weight: bold; background: #f2f2f2; }
        table.detalle-tabla td.grupo { text-align: left; }

        .nota { margin: 11px 0; }
        .nota strong { font-weight: bold; }

        .firma { margin-top: 14px; }
        .firma .espacio { height: 42px; }
        .firma p { margin: 0; line-height: 1.3; }
        .firma .nombre-curador { font-weight: bold; }

        .pie {
            position: fixed;
            bottom: -1.5cm; left: 0; right: 0;
            border-top: 1px solid #999;
            padding-top: 5px;
            font-size: 7pt;
            color: #444;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- Pie institucional repetido en cada página --}}
    <div class="pie">
        Ladrón de Guevara E11-253 – Teléfono: (593 2) 2 976300 Ext. 6011 · Museo de Historia Natural “Gustavo Orcés V.” (MEPN) · Escuela Politécnica Nacional · Quito, Ecuador
    </div>

    {{-- Membrete: escudo EPN (izquierda) + Departamento de Biología (derecha) --}}
    <table class="membrete">
        <tr>
            <td class="izq">
                @if($logoEpnB64)<img class="escudo-epn" src="{{ $logoEpnB64 }}" alt="Escuela Politécnica Nacional">@endif
            </td>
            <td class="der">
                @if($logoDbB64)<img class="logo-db" src="{{ $logoDbB64 }}" alt="Departamento de Biología">@endif
            </td>
        </tr>
    </table>

    {{-- Fecha y número de acta --}}
    <table class="cabecera">
        <tr>
            <td>Quito, {{ $fecha }}</td>
            <td class="der">Acta recepción-depósito No. <strong>{{ $recepcion->numeroSolicitud }}</strong></td>
        </tr>
    </table>

    {{-- Destinatario --}}
    <div class="destinatario">
        <span class="nombre">{{ $investigador }}</span><br>
        @if($depositante?->cargo){{ $depositante->cargo }}<br>@endif
        @if($depositante?->institucion){{ $depositante->institucion }}@endif
    </div>

    <p class="saludo">De mis consideraciones:</p>

    <p>
        Toda vez revisada la documentación requerida para el ingreso de especímenes, incluyendo
        autorización de investigación ({{ $recepcion->nroPermisoRecoleccion ?? '—' }}) y
        movilización ({{ $recepcion->nroPermisoMovilizacion ?? '—' }}), confirmo la recepción del
        material biológico abajo indicado, el cual será depositado en las colecciones temporales del
        Museo de Historia Natural “Gustavo Orcés V.”, Departamento de Biología, Escuela Politécnica
        Nacional (MEPN):
    </p>

    <ul class="detalle">
        <li>
            Detalle:
            <table class="detalle-tabla">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>No. especímenes</th>
                        <th>No. morfoespecies</th>
                        <th>No. lotes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="grupo">{{ $recepcion->grupoAnimal ?? '—' }}</td>
                        <td>{{ $recepcion->nroIndividuos ?? '—' }}</td>
                        <td>{{ $recepcion->nroMorfoespecies ?? '—' }}</td>
                        <td>{{ $recepcion->nroLotes ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </li>
        <li>Origen geográfico: {{ $recepcion->localidad ?? '—' }}</li>
    </ul>

    <p class="nota">
        <strong>Nota</strong>: El MEPN se reserva el derecho de ingresar a la colección principal
        únicamente especímenes en perfecto estado de conservación y con valor científico.
    </p>

    <p>Atentamente,</p>

    <div class="firma">
        <div class="espacio"></div>
        <p class="nombre-curador">{{ $curador ?? 'Curador responsable' }}</p>
        <p>Curador</p>
        <p>Laboratorio de Invertebrados</p>
        <p>Museo de Historia Natural “Gustavo Orcés V.”</p>
        <p>Departamento de Biología</p>
        <p>Escuela Politécnica Nacional</p>
        <p>Quito, Ecuador</p>
    </div>

</body>
</html>
