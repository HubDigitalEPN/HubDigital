<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\Http\Request;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

final class DescargarConectorTokenUsb
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $jar = (string) config('firma-electronica.java_signature_jar');
        abort_unless(is_file($jar), 503, 'El conector de firma aún no está disponible.');
        $motor = new ZipArchive;
        abort_unless($motor->open($jar) === true, 503, 'El conector de firma aún no está disponible.');
        try {
            abort_unless($motor->locateName('org/hubdigital/signatures/TokenUsbBridge.class') !== false,
                503, 'El conector USB aún no está disponible.');
        } finally {
            $motor->close();
        }
        $archivo = DirectorioTemporalHubDigital::crearArchivo('conector-token', 'hubdigital-', 32 * 1024 * 1024);
        $zip = new ZipArchive;
        try {
            if ($zip->open($archivo, ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('No se pudo preparar el conector.');
            foreach ([
                [$jar, 'hubdigital-pdf-signature.jar'],
                [resource_path('token-usb/Iniciar-Conector.ps1'), 'Iniciar-Conector.ps1'],
                [resource_path('token-usb/LEEME.txt'), 'LEEME.txt'],
            ] as [$origen, $destino]) {
                if (! $zip->addFile($origen, $destino)) throw new \RuntimeException('Falta un archivo del conector.');
            }
            if (! $zip->addFromString('ORIGEN.txt', $request->getSchemeAndHttpHost())) throw new \RuntimeException('No se pudo configurar el origen.');
            if (! $zip->close()) throw new \RuntimeException('No se pudo cerrar el paquete del conector.');
        } catch (\Throwable $error) {
            DirectorioTemporalHubDigital::eliminar(dirname($archivo));
            throw $error;
        }

        // El ZIP es temporal y no contiene certificados, PIN ni claves privadas.
        app()->terminating(static fn () => DirectorioTemporalHubDigital::eliminar(dirname($archivo)));
        return response()->download($archivo, 'HubDigital-token-USB.zip', ['Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }
}
