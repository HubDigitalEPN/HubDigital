<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta {{ (string) $acta->codigoPrestamo() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #212121;
            background: #ffffff;
            padding: 32px 40px;
        }

        /* Encabezado */
        .doc-header {
            text-align: center;
            margin-bottom: 20px;
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
        .firma-img {
            display: block;
            max-width: 220px;
            height: 60px;
            margin-bottom: 6px;
        }
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

    {{-- Encabezado --}}
    <div class="doc-header">
        <p class="institution">Laboratorio de Invertebrados de la Escuela Politécnica Nacional</p>
        <h1>Acta de Préstamo de Especímenes</h1>
        <p class="numero">{{ (string) $acta->codigoPrestamo() }}</p>
    </div>

    <hr />

    {{-- Condiciones del préstamo --}}
    <div class="section">
        <h2>Condiciones del préstamo</h2>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="label">Tipo de préstamo</div>
                    <div class="value capitalize">{{ str_replace('_', ' ', $acta->tipoPrestamo()->value) }}</div>
                </td>
                <td>
                    <div class="label">Alcance</div>
                    <div class="value">{{ $acta->alcancePrestamo()->value === 'internacional' ? 'Internacional' : 'Nacional' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">N.º solicitud</div>
                    <div class="value mono">{{ (string) $solicitud->codigoPrestamo() }}</div>
                </td>
                <td>
                    <div class="label">Fecha de inicio</div>
                    <div class="value">{{ $acta->fechaInicio()->format('d/m/Y') }}</div>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <div class="label">Fecha de vencimiento</div>
                    <div class="value">{{ $acta->fechaFin()->format('d/m/Y') }}</div>
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
                    <div class="value">{{ $solicitud->tituloEstudio() }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Institución de adscripción</div>
                    <div class="value">{{ $solicitud->institucionAdscripcion() }}</div>
                </td>
                <td>
                    <div class="label">Línea de investigación</div>
                    <div class="value">{{ $solicitud->lineaInvestigacion() }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="label">Propósito del préstamo</div>
                    <div class="value" style="font-weight: normal;">{{ $solicitud->propositoPrestamo() }}</div>
                </td>
            </tr>
        </table>
    </div>

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
            @if($acta->condicionesGenerales())
                <li>
                    <div class="bullet"><span></span></div>
                    <div class="text">{{ $acta->condicionesGenerales() }}</div>
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
            íntegros al Laboratorio de Invertebrados de la Escuela Politécnica Nacional en la fecha de vencimiento indicada
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
                    <p class="firma-name">Curador responsable</p>
                    <p class="firma-sub">Laboratorio de Invertebrados — EPN</p>
                    <p class="firma-sub">Fecha: ___________________</p>
                </div>
            </td>
            <td>
                <img src="{{ $firmaBase64 }}" class="firma-img" alt="Firma del investigador" />
                <div class="firma-line">
                    <p class="firma-name">Investigador solicitante</p>
                    <p class="firma-sub">{{ $solicitud->institucionAdscripcion() }}</p>
                    <p class="firma-sub">Firmado digitalmente el {{ now()->format('d/m/Y H:i') }}</p>
                </div>
            </td>
        </tr>
    </table>

    {{-- Pie de página --}}
    <div class="footer">
        Documento generado automáticamente por Hub Digital — EPN Colecciones Biológicas &nbsp;|&nbsp;
        Acta: {{ (string) $acta->codigoPrestamo() }}
    </div>

</body>
</html>
