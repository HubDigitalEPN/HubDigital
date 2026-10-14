<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\URL;
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

        $institucionFirmante = $this->institucionFirmante($solicitud);
        $clave = $this->clave($solicitud, $institucionFirmante);
        $indice = 'oficio-pdf-indice:'.$solicitud->id;
        Cache::put($indice, array_values(array_unique([...Cache::get($indice, []), $clave])), now()->addMinutes(20));
        // Revisar y firmar utilizan los mismos bytes mientras siga vigente el oficio.
        return Cache::remember($clave, now()->addMinutes(20), static fn (): string =>
            Pdf::loadView('gestionprestamosrecepciones::pdf.solicitud-deposito', [
                'solicitud' => $solicitud,
                'institucionFirmante' => $institucionFirmante,
                'perfilFirma' => PerfilFirmaPdf::solicitudDepositante(),
            ])->addInfo(['HubDigitalVerificationUrl' => self::urlVerificacion($solicitud)])->setPaper('a4')->output()
        );
    }

    public static function urlVerificacion(SolicitudDepositoEloquentModel $solicitud): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if (!app()->environment(['local', 'testing']) && !str_starts_with($base, 'https://')) {
            throw new \RuntimeException('La verificación móvil requiere una dirección HTTPS pública.');
        }
        return $base.URL::signedRoute('depositos.solicitud.verificar-firma', [
            'id' => $solicitud->id, 'version' => (int) $solicitud->solicitud_documento_version,
        ], null, false);
    }

    public function olvidar(SolicitudDepositoEloquentModel $solicitud): void
    {
        $indice = 'oficio-pdf-indice:'.$solicitud->id;
        foreach (array_unique([...Cache::get($indice, []), $this->clave($solicitud)]) as $clave) Cache::forget($clave);
        Cache::forget($indice);
    }

    private function institucionFirmante(SolicitudDepositoEloquentModel $solicitud): string
    {
        return trim((string) User::query()->whereKey($solicitud->investigador_id)->value('institucion'));
    }

    private function clave(SolicitudDepositoEloquentModel $solicitud, ?string $institucionFirmante = null): string
    {
        $datos = $solicitud->only([
            'id', 'numero', 'created_at', 'solicitud_documento_version',
            'solicitud_nombre_permiso', 'solicitud_tipo_identificacion', 'solicitud_cedula',
            'solicitud_cargo', 'solicitud_grupo', 'solicitud_proyecto', 'solicitud_institucion',
            'solicitud_correo', 'solicitud_oficio', 'solicitud_texto',
        ]);
        $plantilla = module_path('GestionPrestamosRecepciones', 'resources/views/pdf/solicitud-deposito.blade.php');
        $identidad = hash('sha256', serialize([$datos, $institucionFirmante ?? $this->institucionFirmante($solicitud),
            hash_file('sha256', $plantilla), 'oficio-perfil-firmante-qr-v4', config('app.url')]));
        return 'oficio-pdf:'.$solicitud->id.':'.$identidad;
    }
}
