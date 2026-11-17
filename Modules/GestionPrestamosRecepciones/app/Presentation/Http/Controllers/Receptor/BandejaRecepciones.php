<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Receptor;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Recepcion de lotes'])]
final class BandejaRecepciones extends Component
{
    #[Url(as: 'vista')]
    public string $vista = 'pendientes';

    public string $codigoQr = '';

    public string $busqueda = '';

    public function cambiarVista(string $vista): void
    {
        $this->vista = in_array($vista, ['pendientes', 'verificacion', 'historial'], true)
            ? $vista
            : 'pendientes';
    }

    /**
     * Atiende lectores QR tipo teclado y también el código transcrito desde el
     * comprobante del consultor. La resolución siempre usa el QR emitido por EPN.
     */
    public function abrirPorCodigo(): mixed
    {
        $codigo = mb_strtoupper(trim($this->codigoQr));
        $this->codigoQr = $codigo;
        $this->validate([
            'codigoQr' => ['required', 'regex:/^LOTE-[A-Z0-9]{6}$/'],
        ], [
            'codigoQr.required' => 'Escanea o ingresa el código QR del lote.',
            'codigoQr.regex' => 'El código QR no tiene el formato válido de EPN.',
        ]);

        $solicitud = SolicitudDepositoEloquentModel::query()
            ->where('codigo_qr', $codigo)
            ->where('estado', EstadoSolicitudDeposito::AprobadaDocumentalmente->value)
            ->first();

        if ($solicitud === null) {
            $this->addError('codigoQr', 'No existe un lote aprobado con este código QR.');

            return null;
        }

        $this->codigoQr = '';

        return $this->redirectRoute('prestamos.lote.resolver', $solicitud->codigo_qr);
    }

    public function render(UsuarioNombrePort $usuarios): View
    {
        $solicitudesBase = SolicitudDepositoEloquentModel::query()
            ->with('recepcionLote:id,solicitud_deposito_id,firmada_en,acta_firmada_ruta')
            ->where('estado', EstadoSolicitudDeposito::AprobadaDocumentalmente->value)
            ->whereNotNull('codigo_qr')
            ->when(trim($this->busqueda) !== '', function ($query): void {
                $termino = '%'.trim($this->busqueda).'%';
                $query->where(function ($subconsulta) use ($termino): void {
                    $subconsulta
                        ->where('numero', 'ilike', $termino)
                        ->orWhere('codigo_qr', 'ilike', $termino)
                        ->orWhere('nombre_investigador_documento', 'ilike', $termino);
                });
            })
            ->orderByRaw('CASE WHEN entrega_programada_para IS NULL THEN 1 ELSE 0 END')
            ->orderBy('entrega_programada_para')
            ->latest('aprobada_en')
            ->get();

        $recepciones = RecepcionLoteEloquentModel::query()
            ->whereIn('solicitud_deposito_id', $solicitudesBase->pluck('id'))
            ->get()
            ->keyBy('solicitud_deposito_id');

        $solicitudes = $solicitudesBase->filter(function (SolicitudDepositoEloquentModel $solicitud) use ($recepciones): bool {
            $estado = $recepciones->get($solicitud->id)?->estado;

            return match ($this->vista) {
                'verificacion' => $estado === 'En Verificación',
                'historial' => in_array($estado, ['Verificado Físicamente', 'Verificado con Observaciones', 'Recepción Suspendida'], true),
                default => $estado === null || $estado === 'Recepción Suspendida',
            };
        })->values();

        $nombres = $usuarios->obtenerNombres($solicitudes->pluck('investigador_id')->filter()->unique()->values()->all());

        $contadores = [
            'pendientes' => $solicitudesBase->filter(fn (SolicitudDepositoEloquentModel $solicitud) => ($recepciones->get($solicitud->id)?->estado) === null || ($recepciones->get($solicitud->id)?->estado) === 'Recepción Suspendida')->count(),
            'verificacion' => $recepciones->where('estado', 'En Verificación')->count(),
            'historial' => $recepciones->whereIn('estado', ['Verificado Físicamente', 'Verificado con Observaciones', 'Recepción Suspendida'])->count(),
        ];

        return view('gestionprestamosrecepciones::receptor.bandeja-recepciones', compact('solicitudes', 'recepciones', 'nombres', 'contadores'));
    }
}
