<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Exceptions\ModeloIANoDisponibleException;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

final class ExtraccionDatosDocumentoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<string, string>  $documentos  [nombre => ruta_storage]
     */
    public function __construct(
        private readonly string $solicitudId,
        private readonly array $documentos,
    ) {}

    public function handle(
        ExtraccionDatosDocumentoPort $extraccion,
        ValidacionFirmaElectronicaPort $validadorFirma,
        SolicitudDepositoRepositoryInterface $repo,
        TransactionManagerPort $transactionManager,
        EventPublisherPort $eventPublisher,
    ): void {
        SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
            ->update(['extraccion_estado' => 'procesando', 'documentos_procesados' => '[]']);

        try {
            $acumulado = [
                'nroPermisoRecoleccion' => null,
                'nroPermisoMovilizacion' => null,
                'grupoAnimal' => null,
                'provinciaOrigen' => null,
                'localidad' => null,
                'origenDonacion' => null,
                'nombreInvestigador' => null,
            ];

            $procesados = [];

            foreach ($this->documentos as $nombre => $ruta) {
                $parcial = $extraccion->extraerDatos([$nombre => $ruta]);

                foreach ([
                    'nroPermisoRecoleccion' => $parcial->nroPermisoRecoleccion,
                    'nroPermisoMovilizacion' => $parcial->nroPermisoMovilizacion,
                    'grupoAnimal' => $parcial->grupoAnimal,
                    'provinciaOrigen' => $parcial->provinciaOrigen,
                    'localidad' => $parcial->localidad,
                    'origenDonacion' => $parcial->origenDonacion,
                    'nombreInvestigador' => $parcial->nombreInvestigador,
                ] as $campo => $valor) {
                    if ($acumulado[$campo] === null && $valor !== null) {
                        $acumulado[$campo] = $valor;
                    }
                }

                $procesados[] = $nombre;
                $affected = SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                    ->update(['documentos_procesados' => json_encode($procesados)]);
                Log::debug('ExtraccionDatosDocumentoJob: progreso', [
                    'solicitudId' => $this->solicitudId,
                    'procesados' => $procesados,
                    'affected_rows' => $affected,
                    'db_value' => SolicitudDepositoEloquentModel::find($this->solicitudId)?->documentos_procesados,
                ]);
            }

            // Validar firmas electrónicas de cada documento.
            $firmas = [];
            foreach ($this->documentos as $nombre => $ruta) {
                $rutaAbsoluta = Storage::disk('public')->path($ruta);
                $firmas[$nombre] = $validadorFirma->verificarFirma($rutaAbsoluta)->value;
            }

            Log::debug('ExtraccionDatosDocumentoJob: firmas electrónicas', [
                'solicitudId' => $this->solicitudId,
                'firmas' => $firmas,
            ]);

            $datosIntegrados = new DatosIntegradosDocumento(
                nroPermisoRecoleccion: $acumulado['nroPermisoRecoleccion'],
                nroPermisoMovilizacion: $acumulado['nroPermisoMovilizacion'],
                grupoAnimal: $acumulado['grupoAnimal'],
                provinciaOrigen: $acumulado['provinciaOrigen'],
                localidad: $acumulado['localidad'],
                origenDonacion: $acumulado['origenDonacion'],
                nombreInvestigador: $acumulado['nombreInvestigador'],
            );

            $id = SolicitudDepositoId::from($this->solicitudId);
            $solicitud = $repo->buscarPorId($id);

            if ($solicitud === null) {
                throw SolicitudNoEncontradaException::conId($this->solicitudId);
            }

            $solicitud->integrarDatosDeDocumentos(
                datos: $datosIntegrados,
                nombresDocumentos: array_keys($this->documentos),
            );

            $transactionManager->executeTransactional(function () use ($solicitud, $repo, $eventPublisher): void {
                $repo->guardar($solicitud);
                foreach ($solicitud->pullEvents() as $event) {
                    $eventPublisher->publish($event);
                }
            });

            // Persistir firmas después de la integración de dominio para evitar
            // inconsistencia si la transacción anterior falla.
            SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                ->update([
                    'firmas_electronicas' => json_encode($firmas),
                    'extraccion_estado' => 'completada',
                ]);
        } catch (ModeloIANoDisponibleException $e) {
            Log::warning('ExtraccionDatosDocumentoJob: modelo de IA no disponible', [
                'solicitudId' => $this->solicitudId,
                'error' => $e->getMessage(),
            ]);

            SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                ->update(['extraccion_estado' => 'error_modelo']);
        } catch (\Throwable $e) {
            Log::error('ExtraccionDatosDocumentoJob: error al procesar documentos', [
                'solicitudId' => $this->solicitudId,
                'error' => $e->getMessage(),
            ]);

            SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                ->update(['extraccion_estado' => 'fallida']);
        }
    }
}
