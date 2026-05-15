<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DocumentoAdjunto;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

final class EloquentSolicitudDepositoRepository implements SolicitudDepositoRepositoryInterface
{
    public function nextIdentity(): SolicitudDepositoId
    {
        return SolicitudDepositoId::generate();
    }

    public function guardar(SolicitudDeposito $solicitud): void
    {
        $documentosAdjuntos = array_map(
            fn (DocumentoAdjunto $doc) => ['nombre' => $doc->nombre(), 'ruta' => $doc->ruta()],
            $solicitud->documentosAdjuntosParaPersistir()
        );

        SolicitudDepositoEloquentModel::updateOrCreate(
            ['id' => (string) $solicitud->id()],
            [
                'numero' => (string) $solicitud->numero(),
                'investigador_id' => $solicitud->investigadorId(),
                'tipo_tramite' => $solicitud->tipoTramite(),
                'estado' => $solicitud->estado()->value,
                'origen_recoleccion' => $solicitud->origenRecoleccion(),
                'situacion_regulatoria' => $solicitud->situacionRegulatoria(),
                'provincia_origen' => $solicitud->provinciaOrigen(),
                'sin_documentacion' => $solicitud->sinDocumentacionDisponible(),
                'nro_permiso_recoleccion' => $solicitud->nroPermisoRecoleccion(),
                'nro_permiso_movilizacion' => $solicitud->nroPermisoMovilizacion(),
                'grupo_animal' => $solicitud->grupoAnimal(),
                'localidad' => $solicitud->localidad(),
                'origen_donacion' => $solicitud->origenDonacion(),
                'nombre_investigador_documento' => $solicitud->nombreInvestigadorDocumento(),
                'documentos_adjuntos' => array_values($documentosAdjuntos),
                'datos_faltantes' => $solicitud->datosFaltantesParaPersistir(),
                'datos_ingresados_manualmente' => $solicitud->datosIngresadosManualmenterParaPersistir(),
            ]
        );
    }

    public function buscarPorId(SolicitudDepositoId $id): ?SolicitudDeposito
    {
        $model = SolicitudDepositoEloquentModel::find((string) $id);

        if ($model === null) {
            return null;
        }

        return $this->reconstituir($model);
    }

    public function contarPorInvestigadorYTipoEnAnioActual(string $investigadorId, string $tipoTramite): int
    {
        return SolicitudDepositoEloquentModel::where('investigador_id', $investigadorId)
            ->where('tipo_tramite', $tipoTramite)
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->whereYear('created_at', (int) date('Y'))
            ->count();
    }

    public function eliminarBorradoresDe(string $investigadorId): void
    {
        SolicitudDepositoEloquentModel::where('investigador_id', $investigadorId)
            ->where('estado', EstadoSolicitudDeposito::EnBorrador->value)
            ->delete();
    }

    private function reconstituir(SolicitudDepositoEloquentModel $model): SolicitudDeposito
    {
        $documentosAdjuntos = [];
        foreach ($model->documentos_adjuntos ?? [] as $item) {
            $documentosAdjuntos[$item['nombre']] = DocumentoAdjunto::of($item['nombre'], $item['ruta']);
        }

        return SolicitudDeposito::reconstituir(
            id: SolicitudDepositoId::from($model->id),
            numero: NumeroSolicitudDeposito::from($model->numero),
            investigadorId: $model->investigador_id,
            tipoTramite: TipoTramite::from($model->tipo_tramite),
            estado: EstadoSolicitudDeposito::from($model->estado),
            origenRecoleccion: $model->origen_recoleccion,
            situacionRegulatoria: $model->situacion_regulatoria,
            provinciaOrigen: $model->provincia_origen,
            sinDocumentacion: (bool) $model->sin_documentacion,
            nroPermisoRecoleccion: $model->nro_permiso_recoleccion,
            nroPermisoMovilizacion: $model->nro_permiso_movilizacion,
            grupoAnimal: $model->grupo_animal,
            localidad: $model->localidad,
            origenDonacion: $model->origen_donacion,
            documentosAdjuntos: $documentosAdjuntos,
            datosFaltantes: $model->datos_faltantes ?? [],
            nombreInvestigadorDocumento: $model->nombre_investigador_documento,
            datosIngresadosManualmente: $model->datos_ingresados_manualmente ?? [],
        );
    }
}
