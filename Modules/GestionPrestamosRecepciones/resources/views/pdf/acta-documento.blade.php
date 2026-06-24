<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta {{ $acta->numeroPrestamo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #212121;
            background: #ffffff;
            padding: 32px 40px;
        }

        /* Encabezado con logos */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .header-table .logo-left {
            width: 80px;
            text-align: left;
        }
        .header-table .logo-right {
            width: 80px;
            text-align: right;
        }
        .header-table .header-center {
            text-align: center;
        }
        .header-table .logo-img {
            height: 70px;
            width: auto;
        }
        .doc-header .institution {
            font-size: 9px;
            color: #757575;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-header h1 {
            font-size: 16px;
            font-weight: bold;
            color: #212121;
            text-transform: uppercase;
            margin-top: 6px;
        }
        .doc-header .numero {
            font-size: 11px;
            color: #757575;
            font-family: 'Courier New', monospace;
            margin-top: 4px;
        }

        hr { border: none; border-top: 1px solid #E0E0E0; margin: 16px 0; }

        /* Secciones */
        .section { margin-bottom: 20px; }
        .section h2 {
            font-size: 9px;
            font-weight: bold;
            color: #757575;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #E0E0E0;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }

        /* Grid de datos 2 columnas */
        .grid-2 { width: 100%; }
        .grid-2 td {
            width: 50%;
            padding-bottom: 8px;
            vertical-align: top;
        }
        .label { font-size: 9px; color: #757575; margin-bottom: 2px; }
        .value { font-size: 11px; color: #212121; font-weight: 500; }
        .value.mono { font-family: 'Courier New', monospace; }
        .value.capitalize { text-transform: capitalize; }

        /* Tabla especímenes */
        .specimens-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .specimens-table th {
            text-align: left;
            padding: 5px 8px;
            background: #F5F7FA;
            border: 1px solid #E0E0E0;
            font-weight: 600;
            color: #212121;
        }
        .specimens-table td {
            padding: 5px 8px;
            border: 1px solid #E0E0E0;
            color: #212121;
            vertical-align: top;
        }
        .specimens-table tr:nth-child(even) td { background: #F5F7FA; }

        /* Condiciones */
        .conditions-list { margin: 0; padding: 0; list-style: none; }
        .conditions-list li {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .conditions-list li .bullet {
            display: table-cell;
            width: 14px;
            padding-top: 3px;
            vertical-align: top;
        }
        .conditions-list li .bullet span {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #1B365D;
            border-radius: 50%;
        }
        .conditions-list li .text {
            display: table-cell;
            font-size: 10px;
            line-height: 1.6;
            color: #212121;
        }
        .note {
            font-size: 9px;
            color: #757575;
            font-style: italic;
            border-left: 2px solid #E0E0E0;
            padding-left: 8px;
            margin-top: 4px;
        }

        /* Compromiso */
        .commitment {
            font-size: 10px;
            line-height: 1.7;
            color: #212121;
        }

        /* Firmas */
        .firma-table { width: 100%; margin-top: 32px; }
        .firma-table td {
            width: 50%;
            vertical-align: bottom;
            padding-right: 32px;
        }
        .firma-table td:last-child { padding-right: 0; }
        .firma-line {
            border-top: 2px solid #212121;
            padding-top: 6px;
        }
        .firma-name { font-size: 11px; font-weight: 600; color: #212121; }
        .firma-sub  { font-size: 9px; color: #757575; margin-top: 2px; }

        /* Pie de página */
        .footer {
            margin-top: 28px;
            border-top: 1px solid #E0E0E0;
            padding-top: 8px;
            font-size: 8px;
            color: #9E9E9E;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- Logos codificados en base64 para máxima compatibilidad con DomPDF --}}
    @php
        $logoEpnPath = resource_path('logos/logo-epn.jpeg');
        $logoBioPath = resource_path('logos/logo-departamento-biologia-epn.jpeg');
        $logoEpnBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoEpnPath));
        $logoBioBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoBioPath));
    @endphp

    {{-- Encabezado con logos --}}
    <table class="header-table">
        <tr>
            <td class="logo-left">
                <img src="{{ $logoEpnBase64 }}" class="logo-img" alt="Logo EPN" />
            </td>
            <td class="header-center doc-header">
                <p class="institution">Laboratorio de Invertebrados</p>
                <h1>Acta de Préstamo de Especímenes</h1>
                <p class="numero">{{ $acta->numeroPrestamo }}</p>
            </td>
            <td class="logo-right">
                <img src="{{ $logoBioBase64 }}" class="logo-img" alt="Logo Departamento de Biología" />
            </td>
        </tr>
    </table>

    <hr />

    {{-- Condiciones del préstamo --}}
    <div class="section">
        <h2>Condiciones del préstamo</h2>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="label">Tipo de préstamo</div>
                    <div class="value capitalize">{{ str_replace('_', ' ', $acta->tipoPrestamo) }}</div>
                </td>
                <td>
                    <div class="label">Alcance</div>
                    <div class="value">{{ $acta->alcancePrestamo === 'internacional' ? 'Internacional' : 'Nacional' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">N.º solicitud</div>
                    <div class="value mono">{{ $acta->numeroSolicitud }}</div>
                </td>
                <td>
                    <div class="label">Fecha de inicio</div>
                    <div class="value">{{ $acta->fechaInicio->format('d/m/Y') }}</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="label">Fecha de vencimiento</div>
                    <div class="value">{{ $acta->fechaFin->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Investigador solicitante --}}
    <div class="section">
        <h2>Investigador solicitante</h2>
        <table class="grid-2">
            <tr>
                <td colspan="2">
                    <div class="label">Título del estudio</div>
                    <div class="value">{{ $acta->tituloEstudio }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Institución de adscripción</div>
                    <div class="value">{{ $acta->institucionAdscripcion }}</div>
                </td>
                <td>
                    <div class="label">Línea de investigación</div>
                    <div class="value">{{ $acta->lineaInvestigacion }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="label">Propósito del préstamo</div>
                    <div class="value" style="font-weight: normal;">{{ $acta->propositoPrestamo }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Especímenes en préstamo --}}
    @if(count($acta->items))
        <div class="section">
            <h2>Especímenes en préstamo</h2>
            <table class="specimens-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Código de espécimen</th>
                        <th>Cantidad</th>
                        <th>Condiciones específicas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($acta->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $item->codigoExterno }}</td>
                            <td>{{ $item->cantidadSolicitada }}</td>
                            <td>{{ $item->condicionesEspecificas ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Condiciones generales --}}
    <div class="section">
        <h2>Condiciones generales</h2>
        <ul class="conditions-list">
            <li>
                <div class="bullet"><span></span></div>
                <div class="text">
                    En caso de ser de interés para el receptor del préstamo, se autoriza el depósito de réplicas de especies, o morfoespecies, de series de colecciones, en la colección de la institución receptora. El material único, de localidades únicas (singletons) debe ser retornado al MHNGOV, posterior a su estudio.
                    <p class="note"><strong>Nota:</strong> En caso de requerir el depósito de réplicas en la institución receptora, se deben notificar los detalles previamente al Curador, puesto que es necesario firmar un documento de "préstamo permanente".</p>
                </div>
            </li>
            <li>
                <div class="bullet"><span></span></div>
                <div class="text">No se realizan préstamos permanentes de material único. Sin embargo, bajo petición de la institución interesada, los casos excepcionales serán analizados.</div>
            </li>
            <li>
                <div class="bullet"><span></span></div>
                <div class="text">En caso de existir especies nuevas para la ciencia, todos los holotipos, sin excepción, deben retornar al MHNGOV. En el caso de paratipos, el autor(s) de la(s) descripción(es) tiene la libertad de elegir el repositorio de destino de los mismos.</div>
            </li>
            <li>
                <div class="bullet"><span></span></div>
                <div class="text"><strong>Especímenes enviados en etanol:</strong> se agradece la gentileza de que sean retornados montados en alfileres o placas, con sus respectivas etiquetas, esto es, datos de colección e identificación. Esta última, conteniendo únicamente el género y/o especie.</div>
            </li>
            <li>
                <div class="bullet"><span></span></div>
                <div class="text">En caso de requerir extensión del presente préstamo, favor notificarlo con al menos un mes de anticipación al vencimiento de la fecha establecida <em>(Return due date)</em>.</div>
            </li>
            @if($acta->condicionesGenerales)
                <li>
                    <div class="bullet"><span></span></div>
                    <div class="text">{{ $acta->condicionesGenerales }}</div>
                </li>
            @endif
        </ul>
    </div>

    {{-- Compromiso del investigador --}}
    <div class="section">
        <h2>Compromiso del investigador</h2>
        <p class="commitment">
            El investigador solicitante se compromete a utilizar los especímenes en préstamo únicamente para los fines
            declarados en la presente solicitud, a mantenerlos en condiciones adecuadas de conservación, y a devolverlos
            íntegros al Laboratorio de Invertebrados en la fecha de vencimiento indicada
            o antes si el estudio concluye. Cualquier daño, pérdida o uso indebido de los especímenes será responsabilidad
            del investigador y su institución.
        </p>
    </div>

    {{-- Firmas --}}
    <table class="firma-table">
        <tr>
            <td>
                <div style="height: 66px;"></div>
                <div class="firma-line">
                    <p class="firma-name">Adrian Troya</p>
                    <p class="firma-sub">Biólogo</p>
                    <p class="firma-sub">Curador</p>
                    <p class="firma-sub">Laboratorio de Invertebrados</p>
                </div>
            </td>
            <td>
                <div style="height: 66px;"></div>
                <div class="firma-line">
                    <p class="firma-name">{{ $acta->nombreInvestigador ?? 'Investigador solicitante' }}</p>
                    <p class="firma-sub">{{ $acta->institucionAdscripcion }}</p>
                    <p class="firma-sub">Fecha: ___________________</p>
                </div>
            </td>
        </tr>
    </table>

    {{-- Pie de página --}}
    <div class="footer">
        Documento generado automáticamente por Hub Digital — EPN Colecciones Biológicas &nbsp;|&nbsp;
        Acta: {{ $acta->numeroPrestamo }}
    </div>

</body>
</html>
