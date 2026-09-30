<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use Barryvdh\DomPDF\Facade\Pdf;
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
        ] as $campo) {
            if (blank($solicitud->{$campo})) {
                throw ValidationException::withMessages([
                    'solicitud' => 'Completa los datos del oficio antes de generar o firmar el PDF.',
                ]);
            }
        }

        return Pdf::loadView('gestionprestamosrecepciones::pdf.solicitud-deposito', [
            'solicitud' => $solicitud,
            'perfilFirma' => PerfilFirmaPdf::solicitudDepositante(),
        ])->setPaper('a4')->output();
    }
}
