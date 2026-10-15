<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ArchivoLocalDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

function solicitudColaR2ParaPruebas(): SolicitudDepositoEloquentModel
{
    $usuario = User::factory()->create(['rol' => RolUsuario::DEPOSITANTE]);
    return SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(), 'numero' => (string) NumeroSolicitudDeposito::fromSecuencia(random_int(10000, 99999), 'HUB-DEP'),
        'investigador_id' => $usuario->id, 'tipo_tramite' => 'Depósito', 'estado' => 'En Borrador',
        'paso_actual' => 0, 'solicitud_documento_version' => 1,
        'solicitud_nombre_permiso' => 'Depositante Cola R2', 'solicitud_tipo_identificacion' => 'cedula', 'solicitud_cedula' => '1707514251',
        'solicitud_cargo' => DB::table('usuarios.cargos_catalogo')->where('activo', true)->value('nombre'),
        'solicitud_grupo' => array_values(config('depositos.grupos_especimenes'))[0],
        'solicitud_proyecto' => 'Proyecto de cola persistente',
        'solicitud_institucion' => DB::table('usuarios.instituciones_catalogo')->where('activo', true)->value('nombre'),
        'solicitud_correo' => $usuario->email, 'solicitud_oficio' => 'QA-COLA',
    ]);
}

function encolarBytesValidadosParaPruebas(SolicitudDepositoEloquentModel $modelo, string $contenido): string
{
    $temporal = DirectorioTemporalHubDigital::crearArchivo('prueba-cola-firma', 'pdf-');
    file_put_contents($temporal, $contenido);
    $huella = hash('sha256', $contenido);
    $archivo = new ArchivoLocalDeposito($temporal, true, dirname($temporal), [
        'aceptable' => true, 'pdf_seguro' => true, 'pdf_sha256' => $huella,
    ]);
    return DB::transaction(function () use ($modelo, $archivo, $huella): string {
        $ruta = 'solicitudes-deposito/firmadas/'.$modelo->id.'-v1-'.Str::uuid().'.pdf';
        $modelo->forceFill(['solicitud_firmada_ruta' => $ruta, 'solicitud_firmada_sha256' => $huella,
            'solicitud_firmada_en' => now()])->save();
        $id = app(ColaFirmasSolicitudR2::class)->encolar($archivo, $modelo, $ruta, null);
        $modelo->forceFill(['solicitud_firma_metadata' => ['r2_pendiente_id' => $id]])->save();
        return $id;
    });
}
