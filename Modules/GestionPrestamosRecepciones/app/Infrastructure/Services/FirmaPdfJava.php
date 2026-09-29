<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Illuminate\Http\Request;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ArchivoLocalDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Symfony\Component\Process\Process;

/** Transporte efímero: la clave privada y toda operación criptográfica pertenecen a Java. */
final class FirmaPdfJava
{
    public static function reglas(): array
    {
        return [
            'pdf_firmado' => ['required_without:certificado', 'prohibits:certificado', 'file', 'mimes:pdf', 'max:15360'],
            'certificado' => ['required_without:pdf_firmado', 'prohibits:pdf_firmado', 'file', 'extensions:p12,pfx', 'max:5120'],
            'clave_certificado' => ['required_with:certificado', 'string', 'max:512', 'not_regex:/\x00/'],
        ];
    }

    public function preparar(Request $request, string $original, string $perfil): ArchivoLocalDeposito
    {
        if (! $request->hasFile('certificado')) {
            return new ArchivoLocalDeposito($request->file('pdf_firmado')->getRealPath(), false);
        }
        $directorio = DirectorioTemporalHubDigital::crear('firma-java', 24 * 1024 * 1024);
        $p12 = $directorio.DIRECTORY_SEPARATOR.'credencial.p12';
        $salida = $directorio.DIRECTORY_SEPARATOR.'firmado.pdf';
        $proceso = null;
        try {
            if (! copy($request->file('certificado')->getRealPath(), $p12)) {
                throw new \RuntimeException('No se pudo preparar el certificado para Java.');
            }
            @chmod($p12, 0600);
            $proceso = new Process([
                (string) config('firma-electronica.java_binary', 'java'),
                '-Djava.awt.headless=true', '-Xmx384m', '-jar',
                (string) config('firma-electronica.java_signature_jar'), 'sign', '--request-stdin',
            ]);
            $proceso->setEnv(DirectorioTemporalHubDigital::entornoProcesos());
            $proceso->setInput(implode("\0", [$original, $salida, $p12,
                (string) $request->input('clave_certificado'), $perfil]));
            $proceso->setTimeout(60);
            $proceso->run();
            $resultado = json_decode(trim($proceso->getOutput()), true);
            if (! $proceso->isSuccessful() || ! is_array($resultado)
                || ($resultado['status'] ?? null) !== 'firmado' || ! is_file($salida)) {
                throw new \InvalidArgumentException('Java no pudo crear la firma. Verifica el certificado, su contraseña y su vigencia.');
            }
            @chmod($salida, 0600);
            @unlink($p12);

            return new ArchivoLocalDeposito($salida, true, $directorio);
        } catch (\Throwable $error) {
            DirectorioTemporalHubDigital::eliminar($directorio);
            throw $error;
        } finally {
            $proceso?->setInput(null);
            $request->request->remove('clave_certificado');
            $temporalCredencial = $request->file('certificado')?->getRealPath();
            if (is_string($temporalCredencial)) @unlink($temporalCredencial);
            $request->files->remove('certificado');
        }
    }
}
