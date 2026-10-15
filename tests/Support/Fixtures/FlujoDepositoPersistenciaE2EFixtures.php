<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\OriginalActaRecepcionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteOutput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemChecklistRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\LoteRecibidoParaActaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\NuevaSolicitudPorRevisarNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\JavaValidacionFirmaElectronicaAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador\GestionActaRecepcion;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;

/** @return array{archivo: UploadedFile, clave: string} */
function certificadoRealDepositoParaPruebas(): array
{
    $directorio = base_path('.local/secrets');
    $nombre = 'HERNAN AUGUSTO TROYA PROANO 1707514251-241025084137.p12';
    $contenido = @file_get_contents($directorio.DIRECTORY_SEPARATOR.$nombre);
    $textoCredenciales = @file_get_contents($directorio.DIRECTORY_SEPARATOR.'credencialesp12 .txt');
    if (! is_string($contenido) || $contenido === '' || ! is_string($textoCredenciales)) {
        throw new RuntimeException('Faltan el P12 autorizado o sus credenciales locales para la prueba de firma real.');
    }

    foreach (preg_split('/\R/u', $textoCredenciales) ?: [] as $linea) {
        if (preg_match('/^\s*clave\s+(\S+)\s*$/iu', $linea, $coincidencia) === 1) {
            return [
                'archivo' => UploadedFile::fake()->createWithContent($nombre, $contenido),
                'clave' => $coincidencia[1],
            ];
        }
    }

    throw new RuntimeException('No se encontró la clave del P12 autorizado en el archivo local.');
}
