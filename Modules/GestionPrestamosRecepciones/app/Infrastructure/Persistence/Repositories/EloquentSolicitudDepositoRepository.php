<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DocumentoAdjunto;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
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
            ]
        );
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
        );
    }
}
