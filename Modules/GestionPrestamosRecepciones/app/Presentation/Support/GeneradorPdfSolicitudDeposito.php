<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/** Oficio de solicitud basado en la plantilla institucional adjunta. */
final class GeneradorPdfSolicitudDeposito
{
    public function generar(SolicitudDepositoEloquentModel $solicitud): string
    {
        $solicitud->refresh();
        foreach ([
            'solicitud_nombre_permiso', 'solicitud_cedula', 'solicitud_cargo',
            'solicitud_grupo', 'solicitud_proyecto', 'solicitud_institucion',
            'solicitud_correo',
            'solicitud_oficio',
        ] as $campo) {
            if (blank($solicitud->{$campo})) {
                throw ValidationException::withMessages([
                    'solicitud' => 'Completa los datos del oficio antes de generar o firmar el PDF.',
                ]);
            }
        }

        $datos = $solicitud->only([
            'id', 'numero', 'created_at', 'solicitud_documento_version',
            'solicitud_nombre_permiso', 'solicitud_tipo_identificacion', 'solicitud_cedula',
            'solicitud_cargo', 'solicitud_grupo', 'solicitud_proyecto', 'solicitud_institucion',
            'solicitud_correo', 'solicitud_oficio', 'solicitud_texto',
        ]);
        $plantilla = module_path('GestionPrestamosRecepciones', 'resources/views/pdf/solicitud-deposito.blade.php');
        $identidad = hash('sha256', serialize([$datos, hash_file('sha256', $plantilla), 'oficio-texto-v2']));
        // Revisar y firmar utilizan los mismos bytes mientras siga vigente el oficio.
        return Cache::remember('oficio-pdf:'.$solicitud->id.':'.$identidad, now()->addMinutes(20), static fn (): string =>
            Pdf::loadView('gestionprestamosrecepciones::pdf.solicitud-deposito', [
                'solicitud' => $solicitud,
                'perfilFirma' => PerfilFirmaPdf::solicitudDepositante(),
            ])->setPaper('a4')->output()
        );
    }
}
