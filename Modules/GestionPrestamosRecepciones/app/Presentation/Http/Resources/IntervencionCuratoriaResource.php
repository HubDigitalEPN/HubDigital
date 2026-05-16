<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaOutput;

/** @mixin SolicitarIntervencionCuratoriaOutput */
final class IntervencionCuratoriaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'carga_documental_pausada' => $this->cargaDocumentalPausada,
            'notificacion_curador_enviada' => $this->notificacionCuradorEnviada,
            'curador_notificado_id' => $this->curadorNotificadoId,
        ];
    }
}
