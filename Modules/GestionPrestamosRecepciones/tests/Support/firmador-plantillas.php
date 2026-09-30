<?php

declare(strict_types=1);

// Prueba de integracion sin base de datos ni credenciales reales.
// php .../firmador-plantillas.php generar|validar /tmp/directorio-fixtures

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\JavaValidacionFirmaElectronicaAdapter;
use Modules\GestionPrestamosRecepciones\Presentation\Support\PerfilFirmaPdf;

$raiz = dirname(__DIR__, 4);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$modo = $argv[1] ?? '';
$directorio = $argv[2] ?? '';
if (! in_array($modo, ['generar', 'validar'], true) || $directorio === '') {
    throw new RuntimeException('Indica generar|validar y un directorio temporal de fixtures.');
}

if ($modo === 'generar') {
    if (! is_dir($directorio) && ! mkdir($directorio, 0700, true)) {
        throw new RuntimeException('No se pudo crear el directorio de fixtures.');
    }
    $depositante = (object) [
        'first_name' => 'Depositante', 'last_name' => 'de prueba', 'nombre' => 'Depositante de prueba',
        'cargo' => 'Investigador de prueba', 'institucion' => 'Institucion sintetica',
    ];
    $solicitud = (object) [
        'numero' => 'PRUEBA-DEPOSITO-001', 'tipo_tramite' => 'Deposito',
        'solicitud_documento_version' => 1, 'localidad' => 'Localidad sintetica',
        'created_at' => \Carbon\CarbonImmutable::parse('2026-01-01'),
        'solicitud_oficio' => 'PRUEBA-001',
        'solicitud_nombre_permiso' => 'Depositante de prueba',
        'solicitud_cedula' => '1700000000',
        'solicitud_cargo' => 'Investigador de prueba',
        'solicitud_grupo' => 'Insectos',
        'solicitud_proyecto' => 'Proyecto sintético',
        'solicitud_institucion' => 'Institución sintética',
        'solicitud_correo' => 'depositante@example.test',
    ];
    $registros = collect(array_map(static fn (int $indice): object => (object) [
        'nombre_corregido' => null, 'nombre_cientifico' => 'Insecta especie '.$indice,
        'datos_dwc' => ['catalogNumber' => 'PRUEBA-'.$indice, 'eventDate' => '2026-01-01'],
    ], range(1, 35)));
    $datosMepn = [];
    foreach (range(1, 15) as $indice) {
        $datosMepn['Campo MEPN '.$indice] = 'Valor sintetico '.$indice;
    }
    $perfilFirma = PerfilFirmaPdf::solicitudDepositante();
    $huellaExpediente = hash('sha256', 'expediente-sintetico');
    $pdfSolicitud = Pdf::loadView('gestionprestamosrecepciones::pdf.solicitud-deposito', compact(
        'solicitud', 'depositante', 'registros', 'datosMepn', 'perfilFirma', 'huellaExpediente',
    ))->setPaper('a4')->output();
    file_put_contents($directorio.'/solicitud-original.pdf', $pdfSolicitud);

    $recepcion = (object) [
        'numeroSolicitud' => 'PRUEBA-ACTA-001', 'nroPermisoRecoleccion' => 'PRUEBA-R',
        'nroPermisoMovilizacion' => 'PRUEBA-M', 'grupoAnimal' => 'Insecta',
        'nroIndividuos' => 35, 'nroMorfoespecies' => 2, 'nroLotes' => 1,
        'localidad' => 'Localidad sintetica', 'verificadoEn' => new DateTimeImmutable('2026-01-01 10:00:00'),
        'tipoTramite' => 'Depósito', 'estadoRecepcion' => 'Conforme', 'observaciones' => [],
    ];
    $pdfActa = Pdf::loadView('gestionprestamosrecepciones::pdf.acta-recepcion', [
        'recepcion' => $recepcion, 'depositante' => $depositante, 'investigador' => $depositante->nombre,
        'curador' => 'Curador de prueba', 'receptor' => 'Receptor de prueba',
        'fecha' => '1 de enero de 2026', 'perfilFirma' => PerfilFirmaPdf::actaRecepcionCurador(),
        'versionActa' => 1,
    ])->setPaper('a4')->output();
    file_put_contents($directorio.'/acta-original.pdf', $pdfActa);
    fwrite(STDOUT, "Plantillas reales DomPDF generadas con datos sinteticos.\n");
    exit(0);
}

config()->set('firma-electronica.java_signature_trust_dir', $directorio);
$validador = new JavaValidacionFirmaElectronicaAdapter;
foreach (['solicitud', 'acta'] as $nombre) {
    $detalle = $validador->verificarFirmaDetallada(
        $directorio.'/'.$nombre.'-firmada.pdf',
        $directorio.'/'.$nombre.'-original.pdf',
    );
    // El motor confía solo en la raíz sintética creada en este directorio temporal.
    if (! $detalle->esAceptable()) {
        fwrite(STDERR, $nombre.': '.json_encode([
            'integridad' => $detalle->integridadCriptografica,
            'cobertura' => $detalle->documentoCompletoFirmado,
            'contenido' => $detalle->contenidoOficialCoincide,
            'vigencia' => $detalle->certificadoVigente,
            'formato' => $detalle->certificado['tipo_firma'] ?? null,
            'error' => $detalle->error,
        ], JSON_THROW_ON_ERROR)."\n");
        exit(1);
    }
    fwrite(STDOUT, $nombre.": firma valida, bloque nominal exacto y contenido oficial intacto.\n");
}

// Aisla el control de contenido: una reescritura de prueba tambien invalida el CMS.
foreach (['desplazada', 'apariencia-vacia', 'anotacion-extra', 'contenido-alterado'] as $alteracion) {
    $ruta = $directorio.'/solicitud-'.$alteracion.'.pdf';
    if (! is_file($ruta)) {
        throw new RuntimeException('Falta el fixture de alteración '.$alteracion.'.');
    }
    $detalle = $validador->verificarFirmaDetallada($ruta, $directorio.'/solicitud-original.pdf');
    if ($detalle->contenidoOficialCoincide || $detalle->resultado === \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::VerificacionNoDisponible) {
        fwrite(STDERR, $alteracion.": el control de contenido no rechazo la alteracion.\n");
        exit(1);
    }
    fwrite(STDOUT, $alteracion.": alteracion rechazada por el control de contenido.\n");
}
