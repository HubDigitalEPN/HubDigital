<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Domain\Entities\AlertaSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlertaSolicitudId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DocumentoAdjunto;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRevisionAlerta;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrioridadSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoAlerta;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\AlertaSolicitudEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Implementación Eloquent del repositorio de solicitudes de depósito.
 *
 * Persiste y recupera el agregado {@see SolicitudDeposito} utilizando el modelo
 * Eloquent {@see SolicitudDepositoEloquentModel}.
 */
final class EloquentSolicitudDepositoRepository implements SolicitudDepositoRepositoryInterface
{
    /**
     * Genera un nuevo identificador único para una solicitud de depósito.
     */
    public function nextIdentity(): SolicitudDepositoId
    {
        return SolicitudDepositoId::generate();
    }

    /**
     * Genera el siguiente número secuencial para una solicitud de depósito.
     */
    public function nextNumero(): NumeroSolicitudDeposito
    {
        $maxSeq = DB::selectOne(
            "SELECT MAX(CAST(SUBSTRING(numero FROM 10) AS INTEGER)) AS max_seq
             FROM recepciones.solicitudes_deposito
             WHERE numero LIKE 'MEPN-INV-%'"
        );

        return NumeroSolicitudDeposito::fromSecuencia(($maxSeq->max_seq ?? 0) + 1);
    }

    /**
     * Persiste la solicitud de depósito en la base de datos.
     */
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
                'nro_individuos' => $solicitud->nroIndividuos() !== null ? (int) $solicitud->nroIndividuos() : null,
                'nro_morfoespecies' => $solicitud->nroMorfoespecies() !== null ? (int) $solicitud->nroMorfoespecies() : null,
                'nro_lotes' => $solicitud->nroLotes() !== null ? (int) $solicitud->nroLotes() : null,
                'curador_responsable' => $solicitud->curadorResponsable(),
                'aprobada_en' => $solicitud->aprobadaEn(),
                'rechazada_en' => $solicitud->rechazadaEn(),
                'codigo_qr' => $solicitud->codigoQR() !== null ? (string) $solicitud->codigoQR() : null,
                'comentario_curador' => $solicitud->comentarioCurador(),
                'prioridad' => $solicitud->prioridad()->value,
                'acta_transferencia_dominio' => $solicitud->actaTransferenciaDominio() !== null
                    ? [
                        'nombre' => $solicitud->actaTransferenciaDominio()->nombre(),
                        'ruta' => $solicitud->actaTransferenciaDominio()->ruta(),
                    ]
                    : null,
            ]
        );

        // Sincronizar la colección de alertas (eliminar las que ya no están, upsert vigentes).
        $idsVigentes = [];
        foreach ($solicitud->alertasParaPersistir() as $alerta) {
            $idsVigentes[] = (string) $alerta->id();
            AlertaSolicitudEloquentModel::updateOrCreate(
                ['id' => (string) $alerta->id()],
                [
                    'solicitud_deposito_id' => (string) $solicitud->id(),
                    'tipo' => $alerta->tipo()->value,
                    'justificacion_investigador' => $alerta->justificacionInvestigador(),
                    'estado_revision' => $alerta->estadoRevision()->value,
                ]
            );
        }

        AlertaSolicitudEloquentModel::where('solicitud_deposito_id', (string) $solicitud->id())
            ->whereNotIn('id', $idsVigentes)
            ->delete();
    }

    /**
     * Busca una solicitud de depósito por su identificador único.
     */
    public function buscarPorId(SolicitudDepositoId $id): ?SolicitudDeposito
    {
        $model = SolicitudDepositoEloquentModel::find((string) $id);

        if ($model === null) {
            return null;
        }

        return $this->reconstituir($model);
    }

    /**
     * Cuenta cuántas solicitudes ha realizado un investigador de un tipo específico en el año actual.
     */
    public function contarPorInvestigadorYTipoEnAnioActual(string $investigadorId, string $tipoTramite): int
    {
        return SolicitudDepositoEloquentModel::where('investigador_id', $investigadorId)
            ->where('tipo_tramite', $tipoTramite)
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->whereYear('created_at', (int) date('Y'))
            ->count();
    }

    /**
     * Elimina todas las solicitudes en estado borrador de un investigador.
     */
    public function eliminarBorradoresDe(string $investigadorId): void
    {
        SolicitudDepositoEloquentModel::where('investigador_id', $investigadorId)
            ->where('estado', EstadoSolicitudDeposito::EnBorrador->value)
            ->delete();
    }

    /**
     * Reconstituye la entidad de dominio a partir del modelo Eloquent.
     */
    private function reconstituir(SolicitudDepositoEloquentModel $model): SolicitudDeposito
    {
        $documentosAdjuntos = [];
        foreach ($model->documentos_adjuntos ?? [] as $item) {
            $documentosAdjuntos[$item['nombre']] = DocumentoAdjunto::of($item['nombre'], $item['ruta']);
        }

        $model->loadMissing('alertas');
        $alertas = [];
        foreach ($model->alertas as $alertaModel) {
            $alertas[] = AlertaSolicitud::reconstituir(
                id: AlertaSolicitudId::from($alertaModel->id),
                tipo: TipoAlerta::from($alertaModel->tipo),
                justificacionInvestigador: $alertaModel->justificacion_investigador,
                estadoRevision: EstadoRevisionAlerta::from($alertaModel->estado_revision),
            );
        }

        $actaTransferenciaDominio = $model->acta_transferencia_dominio !== null
            ? DocumentoAdjunto::of(
                $model->acta_transferencia_dominio['nombre'],
                $model->acta_transferencia_dominio['ruta'],
            )
            : null;

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
            nroIndividuos: $model->nro_individuos !== null ? (string) $model->nro_individuos : null,
            nroMorfoespecies: $model->nro_morfoespecies !== null ? (string) $model->nro_morfoespecies : null,
            nroLotes: $model->nro_lotes !== null ? (string) $model->nro_lotes : null,
            curadorResponsable: $model->curador_responsable,
            aprobadaEn: $model->aprobada_en !== null ? DateTimeImmutable::createFromInterface($model->aprobada_en) : null,
            rechazadaEn: $model->rechazada_en !== null ? DateTimeImmutable::createFromInterface($model->rechazada_en) : null,
            codigoQR: $model->codigo_qr !== null ? CodigoQRLote::from($model->codigo_qr) : null,
            comentarioCurador: $model->comentario_curador,
            prioridad: PrioridadSolicitud::from($model->prioridad ?? 'Normal'),
            actaTransferenciaDominio: $actaTransferenciaDominio,
            alertas: $alertas,
        );
    }
}
