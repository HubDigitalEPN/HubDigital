<?php

declare(strict_types=1);

// Prueba de humo del mismo camino de inspección usado al cargar depósitos.
// Usa PDF sintéticos efímeros: no toca expedientes, R2 ni datos de usuarios.
$proyecto = dirname(__DIR__, 3);
require $proyecto.'/vendor/autoload.php';
$app = require $proyecto.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$archivoValido = tempnam(sys_get_temp_dir(), 'hd-pdf-ok-');
$archivoFalso = tempnam(sys_get_temp_dir(), 'hd-pdf-no-');
$directorioFirma = sys_get_temp_dir().'/hd-firma-'.bin2hex(random_bytes(8));
if ($archivoValido === false || $archivoFalso === false || ! mkdir($directorioFirma, 0700)) {
    if ($archivoValido !== false) {
        @unlink($archivoValido);
    }
    if ($archivoFalso !== false) {
        @unlink($archivoFalso);
    }
    fwrite(STDERR, "NO OK solución PDF: no se pudieron crear temporales.\n");
    exit(1);
}

$fallo = null;
$motorPrueba = null;
$directorioOficial = null;
$configuracionFirma = config('firma-electronica');
try {
    $pdf = new \setasign\Fpdi\Fpdi;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, 'Documento sintetico HubDigital');
    file_put_contents($archivoValido, $pdf->Output('S'));

    app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ValidadorPdfDeposito::class)
        ->validar($archivoValido);

    $contenidoValido = file_get_contents($archivoValido);
    if ($contenidoValido === false) {
        throw new \RuntimeException('No se pudo leer el PDF válido de prueba.');
    }
    file_put_contents($archivoFalso, 'MZ'.$contenidoValido);
    try {
        app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ValidadorPdfDeposito::class)
            ->validar($archivoFalso);
        throw new \RuntimeException('Se aceptó un PDF con bytes anteriores a su número mágico.');
    } catch (\InvalidArgumentException $error) {
        if (! str_contains($error->getMessage(), 'número mágico')) {
            throw $error;
        }
    }

    file_put_contents($archivoFalso, "%PDF-1.7\ncontenido que no es un PDF completo");
    try {
        app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ValidadorPdfDeposito::class)
            ->validar($archivoFalso);
        throw new \RuntimeException('Se aceptó un PDF falso.');
    } catch (\InvalidArgumentException) {
        // Rechazo esperado.
    }

    $firma = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\JavaValidacionFirmaElectronicaAdapter::class)
        ->verificarFirma($archivoValido);
    if ($firma !== \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::SinFirma) {
        throw new \RuntimeException('El adaptador PHP/Java no detectó el PDF sin firma.');
    }

    // Certificado sintético de un día, confiable solo para esta prueba y
    // eliminado al terminar. Comprueba la firma real a través del adaptador.
    $opensslConfig = $directorioFirma.'/openssl.cnf';
    file_put_contents($opensslConfig, "[req]\ndistinguished_name=dn\n[dn]\n[v3_ca]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,digitalSignature\n");
    $opciones = ['config' => $opensslConfig, 'digest_alg' => 'sha256'];
    $clave = openssl_pkey_new($opciones + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $solicitud = $clave === false ? false : openssl_csr_new(
        ['commonName' => 'HubDigital QA', 'organizationName' => 'HubDigital', 'countryName' => 'EC'],
        $clave,
        $opciones,
    );
    $certificado = $solicitud === false ? false : openssl_csr_sign(
        $solicitud, null, $clave, 1, $opciones + ['x509_extensions' => 'v3_ca'],
    );
    if ($certificado === false || ! openssl_x509_export($certificado, $certPem)
        || ! openssl_pkcs12_export($certificado, $p12, $clave, 'prueba-temporal')) {
        throw new \RuntimeException('No se pudo generar el certificado sintético de prueba.');
    }
    file_put_contents($directorioFirma.'/qa.crt', $certPem);
    file_put_contents($directorioFirma.'/qa.p12', $p12);
    file_put_contents($directorioFirma.'/clave.txt', 'prueba-temporal');

    $firmado = $directorioFirma.'/firmado.pdf';
    $jar = (string) config('firma-electronica.java_signature_jar');
    $firmador = new \Symfony\Component\Process\Process([
        'java', '-Djava.awt.headless=true', '-Xms24m', '-Xmx160m', '-XX:+UseSerialGC', '-XX:ActiveProcessorCount=2',
        '-jar', $jar, 'sign', $archivoValido, $firmado,
        $directorioFirma.'/qa.p12', $directorioFirma.'/clave.txt',
    ]);
    $firmador->setTimeout(30);
    $firmador->mustRun();
    if ((json_decode(trim($firmador->getOutput()), true)['status'] ?? null) !== 'firmado') {
        throw new \RuntimeException('El firmador Java no confirmó el PDF sintético.');
    }

    config()->set('firma-electronica.java_signature_trust_dir', $directorioFirma);
    if (in_array(PHP_OS_FAMILY, ['Linux', 'Windows'], true)) {
        // El motor de producción debe rechazar esta confianza temporal.
        // Preparar otro motor privado conserva ese aislamiento y el plazo de
        // tres segundos del adaptador, sin medir el arranque de una JVM fría.
        $entorno = \Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital::entornoProcesos();
        $socketPrueba = $directorioFirma.'/firma.sock';
        $windows = PHP_OS_FAMILY === 'Windows';
        $opcionesTransporte = [];
        $transporte = ['serve', $socketPrueba];
        if ($windows) {
            if (! app()->environment('local')) {
                throw new \RuntimeException('La prueba del transporte Windows requiere el entorno local.');
            }
            $claveMotor = $directorioFirma.'/motor.key';
            file_put_contents($claveMotor, random_bytes(32));
            $opcionesTransporte = ['-Dhubdigital.signature.local_port=0'];
            $transporte = ['serve-local', $claveMotor];
        }
        $motorPrueba = new \Symfony\Component\Process\Process([
            (string) config('firma-electronica.java_binary', 'java'),
            '-Djava.awt.headless=true', '-Xms24m', '-Xmx128m', '-XX:+UseSerialGC', '-XX:ActiveProcessorCount=2',
            '-Djava.io.tmpdir='.$entorno['TMPDIR'],
            '-Dcom.sun.security.ocsp.timeout=2', '-Dcom.sun.security.crl.timeout=2', '-Dhubdigital.revocation.timeout=2',
            '-Dhubdigital.pdf.max_pages='.config('firma-electronica.max_pages', 40),
            '-Dhubdigital.pdf.max_page_points='.config('firma-electronica.max_page_points', 1440),
            '-Dhubdigital.pdf.render_dpi='.config('firma-electronica.render_dpi', 110),
            '-Dhubdigital.pdf.max_render_pixels='.config('firma-electronica.max_render_pixels', 100000000),
            ...$opcionesTransporte,
            '-jar', $jar, ...$transporte,
        ], null, $entorno + [
            'HUBDIGITAL_SIGNATURE_TRUST_DIR' => $directorioFirma,
            'HUBDIGITAL_SIGNATURE_DIAGNOSTICS' => '1',
        ]);
        $motorPrueba->setTimeout(null);
        $motorPrueba->start();
        // Mismo margen de preparación que verify-signature-worker.php en la
        // VM pequeña. Este arranque precede al plazo de validación de 3 s.
        $limitePreparacion = microtime(true) + 90;
        do {
            if ($windows) {
                $salidaMotor = $motorPrueba->getOutput();
                $preparado = preg_match('/LOCAL_SIGNATURE_PORT=(\d+)\RLOCAL_SIGNATURE_READY/', $salidaMotor, $puertoMotor) === 1;
            } else {
                clearstatcache(true, $socketPrueba);
                $preparado = file_exists($socketPrueba) && ! is_link($socketPrueba)
                    && (fileperms($socketPrueba) & 0777) === 0600;
            }
            if ($preparado || ! $motorPrueba->isRunning()) {
                break;
            }
            usleep(100000);
        } while (microtime(true) < $limitePreparacion);
        if (! $preparado || ! $motorPrueba->isRunning()) {
            $motivo = $motorPrueba->isRunning()
                ? 'se agotaron los 90 segundos de preparación'
                : 'el proceso terminó con código '.($motorPrueba->getExitCode() ?? 'desconocido');
            throw new \RuntimeException('No se pudo preparar el motor privado de la prueba de firma: '.$motivo.'.');
        }
        config()->set('firma-electronica.worker_enabled', true);
        if ($windows) {
            config()->set('firma-electronica.worker_local_key', $claveMotor);
            config()->set('firma-electronica.worker_local_port', (int) $puertoMotor[1]);
        } else {
            config()->set('firma-electronica.worker_socket', $socketPrueba);
        }
    }
    $adaptador = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\JavaValidacionFirmaElectronicaAdapter::class);
    // El camino oficial exige certificado de persona (no CA) y los dos
    // marcadores del perfil. La identidad sigue siendo sintética y efímera.
    file_put_contents($opensslConfig, "\n[v3_signer]\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,nonRepudiation\n", FILE_APPEND);
    $clavePersona = openssl_pkey_new($opciones + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csrPersona = $clavePersona === false ? false : openssl_csr_new(
        ['commonName' => 'Persona QA', 'organizationName' => 'HubDigital', 'countryName' => 'EC'], $clavePersona, $opciones,
    );
    $certPersona = $csrPersona === false ? false : openssl_csr_sign(
        $csrPersona, $certificado, $clave, 1, $opciones + ['x509_extensions' => 'v3_signer'], 2,
    );
    if ($certPersona === false || ! openssl_pkcs12_export($certPersona, $p12Persona, $clavePersona,
        'prueba-temporal', ['extracerts' => [$certPem]])) {
        throw new \RuntimeException('No se pudo preparar la identidad sintética de persona.');
    }
    $directorioOficial = \Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital::crear('firma-oficial-qa');
    file_put_contents($directorioOficial.'/persona.p12', $p12Persona);
    $oficio = new \setasign\Fpdi\Fpdi;
    $oficio->AddPage();
    $oficio->SetFont('Helvetica', '', 12);
    $oficio->Cell(0, 10, 'Oficio sintetico para medir firma oficial');
    // FPDF escribe [x,y,x+w,y-h]. Partir del borde inferior y usar h negativo
    // conserva el orden PDF canónico que exige el perfil institucional.
    $oficio->Link(20, 245, 170, -35, 'https://firmas.hubdigital.invalid/bloques/solicitud-deposito/depositante/v1');
    $oficio->Link(22, 243, 166, -31, 'https://firmas.hubdigital.invalid/zonas/solicitud-deposito/depositante/v1');
    $contenidoOficio = $oficio->Output('S');
    $originalOficial = $directorioOficial.'/oficio.pdf';
    file_put_contents($originalOficial, $contenidoOficio);
    $clientePreparado = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Services\ClienteFirmadorPreparado::class);
    $clientePreparado->prepararOriginal($contenidoOficio);
    // El respaldo CLI frío no debe ocultar una regresión de latencia.
    $inicioCreacion = microtime(true);
    $salidaPreparada = $directorioOficial.'/firmado-preparado.pdf';
    $creacionPreparada = $clientePreparado->firmar($originalOficial, $salidaPreparada,
        $directorioOficial.'/persona.p12', 'prueba-temporal', 'solicitud-deposito:depositante:v1');
    $duracionCreacion = microtime(true) - $inicioCreacion;
    if (! is_array($creacionPreparada) || ! is_file($salidaPreparada)
        || ($creacionPreparada['aceptable'] ?? false) !== true
        || ! in_array($creacionPreparada['status'] ?? null, ['firmado', 'firmado_sin_revocacion'], true)) {
        $diagnostico = is_array($creacionPreparada)
            ? json_encode(array_intersect_key($creacionPreparada, array_flip([
                'status', 'reason', 'aceptable', 'contenido_oficial_coincide', 'pdf_seguro',
            ])), JSON_UNESCAPED_UNICODE)
            : 'sin respuesta compatible';
        throw new \RuntimeException('El motor preparado no confirmó la creación y comprobación de la firma: '.$diagnostico
            .($motorPrueba !== null ? "\nMotor sintético: ".$motorPrueba->getErrorOutput() : ''));
    }
    if ($duracionCreacion > 3) {
        throw new \RuntimeException('La creación y comprobación de la firma superó tres segundos.');
    }
    $inicioValidacion = microtime(true);
    $resultado = $adaptador->verificarFirma($firmado);
    $duracionFirma = microtime(true) - $inicioValidacion;
    if (! in_array($resultado, [
        \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::Firmado,
        \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::FirmadoSinRevocacion,
    ], true)) {
        throw new \RuntimeException('El adaptador PHP/Java rechazó una firma sintética íntegra: '.$resultado->value);
    }
    if ($duracionFirma > 3) {
        throw new \RuntimeException('La validación de la firma íntegra superó tres segundos.');
    }

    $alterado = $directorioFirma.'/alterado.pdf';
    $contenido = file_get_contents($firmado);
    if ($contenido === false) {
        throw new \RuntimeException('No se pudo leer el PDF firmado de prueba.');
    }
    $contenido[7] = $contenido[7] === '6' ? '7' : '6';
    file_put_contents($alterado, $contenido);
    $inicioValidacion = microtime(true);
    $resultadoAlterado = $adaptador->verificarFirma($alterado);
    $duracionAlterado = microtime(true) - $inicioValidacion;
    if ($resultadoAlterado !== \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::FirmaInvalida) {
        throw new \RuntimeException('El adaptador PHP/Java aceptó un PDF firmado y alterado.');
    }
    if ($duracionAlterado > 3) {
        throw new \RuntimeException('La validación de la firma alterada superó tres segundos.');
    }

    echo "OK solución PDF depósitos: número mágico en byte cero, estructura y contenido activo comprobados; PDF falso rechazado; PHP/Java verificó firma válida, alterada y ausente.\n";
    echo 'Tiempos de firma: creación y comprobación='.round($duracionCreacion * 1000).' ms; íntegra='.round($duracionFirma * 1000).' ms; alterada='.round($duracionAlterado * 1000)." ms.\n";
} catch (\Throwable $error) {
    $fallo = 'NO OK solución PDF depósitos: '.$error->getMessage();
    // Diagnóstico de los procesos sintéticos: clases y tiempos, nunca credenciales ni PDF.
    for ($causa = $error->getPrevious(), $nivel = 0; $causa !== null && $nivel < 4; $causa = $causa->getPrevious(), $nivel++) {
        $fallo .= ' ['.get_class($causa);
        if ($causa instanceof \Symfony\Component\Process\Exception\ProcessTimedOutException) {
            $fallo .= '; limite='.$causa->getExceededTimeout().' s';
        }
        $fallo .= ']';
    }
} finally {
    if ($motorPrueba !== null) {
        $motorPrueba->stop(2);
    }
    if ($directorioOficial !== null) {
        \Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital::eliminar($directorioOficial);
    }
    config()->set('firma-electronica', $configuracionFirma);
    @unlink($archivoValido);
    @unlink($archivoFalso);
    foreach (glob($directorioFirma.'/*') ?: [] as $temporal) {
        @unlink($temporal);
    }
    @rmdir($directorioFirma);
}
if ($fallo !== null) {
    fwrite(STDERR, $fallo."\n");
    exit(1);
}
