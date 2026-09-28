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
        'java', '-jar', $jar, 'sign', $archivoValido, $firmado,
        $directorioFirma.'/qa.p12', $directorioFirma.'/clave.txt',
    ]);
    $firmador->setTimeout(30);
    $firmador->mustRun();
    if ((json_decode(trim($firmador->getOutput()), true)['status'] ?? null) !== 'firmado') {
        throw new \RuntimeException('El firmador Java no confirmó el PDF sintético.');
    }

    config()->set('firma-electronica.java_signature_trust_dir', $directorioFirma);
    $adaptador = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\JavaValidacionFirmaElectronicaAdapter::class);
    $resultado = $adaptador->verificarFirma($firmado);
    if (! in_array($resultado, [
        \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::Firmado,
        \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::FirmadoSinRevocacion,
    ], true)) {
        throw new \RuntimeException('El adaptador PHP/Java rechazó una firma sintética íntegra: '.$resultado->value);
    }

    $alterado = $directorioFirma.'/alterado.pdf';
    $contenido = file_get_contents($firmado);
    if ($contenido === false) {
        throw new \RuntimeException('No se pudo leer el PDF firmado de prueba.');
    }
    $contenido[7] = $contenido[7] === '6' ? '7' : '6';
    file_put_contents($alterado, $contenido);
    if ($adaptador->verificarFirma($alterado) !== \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma::FirmaInvalida) {
        throw new \RuntimeException('El adaptador PHP/Java aceptó un PDF firmado y alterado.');
    }

    echo "OK solución PDF depósitos: número mágico en byte cero, estructura y contenido activo comprobados; PDF falso rechazado; PHP/Java verificó firma válida, alterada y ausente.\n";
} catch (\Throwable $error) {
    $fallo = 'NO OK solución PDF depósitos: '.$error->getMessage();
} finally {
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
