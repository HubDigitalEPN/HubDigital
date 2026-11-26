<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemChecklistRecepcion;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;

#[Layout('layouts.app', params: ['title' => 'Registro del contenido recibido'])]
final class ContenidoRecepcion extends Component
{
    #[Locked] public string $id;
    public string $registroId = '';
    public string $identificador = '';
    public int $cantidad = 1;
    public string $scientificName = '';
    public string $recordNumber = '';
    public string $preparations = '';
    public string $observaciones = '';
    public bool $contenidoCompleto = false;
    public array $criterios = [];

    public function boot(): void
    {
        abort_unless(app(\App\Support\AccesoMenu::class)->permite('prestamos.receptor.depositos', 'recibir'), 403);
    }

    public function mount(string $id): void
    {
        $this->id = $id;
        $this->solicitud();
    }

    private function solicitud(bool $bloquear = false): Solicitud
    {
        $s = ($bloquear ? Solicitud::lockForUpdate() : Solicitud::query())->findOrFail($this->id);
        abort_unless($s->recepcion_qr_en && !$s->recepcion_qr_rechazada_en && $s->estado === 'Aprobada Documentalmente', 409, 'Primero debe aprobarse la recepción del material mediante su QR.');
        return $s;
    }

    public function guardarRegistro(): void
    {
        $this->validate([
            'registroId' => 'nullable|uuid', 'identificador' => 'required|string|max:255',
            'cantidad' => 'required|integer|min:1|max:10000000', 'scientificName' => 'required|string|max:255',
            'recordNumber' => 'nullable|string|max:255', 'preparations' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string|max:3000',
        ]);
        DB::transaction(function (): void {
            $s = $this->solicitud(true);
            abort_if($s->contenido_validado_en, 409, 'El contenido ya fue validado y está cerrado.');
            $id = $this->registroId ?: (string) Str::uuid();
            $consulta = DB::table('recepciones.contenido_recepcion')->where('solicitud_id', $this->id);
            if ($this->registroId !== '') abort_unless((clone $consulta)->where('id', $id)->exists(), 404);
            if ((clone $consulta)->where('identificador', trim($this->identificador))->where('id', '!=', $id)->exists()) {
                $this->addError('identificador', 'Este identificador ya está registrado en la recepción.');
                return;
            }
            $valores = [
                'solicitud_id' => $this->id, 'identificador' => trim($this->identificador), 'cantidad' => $this->cantidad,
                'datos_dwc' => json_encode(['scientificName' => trim($this->scientificName), 'recordNumber' => trim($this->recordNumber), 'preparations' => trim($this->preparations)], JSON_THROW_ON_ERROR),
                'observaciones' => trim($this->observaciones), 'validado_por' => auth()->id(), 'validado_en' => now(), 'updated_at' => now(),
            ];
            if ($this->registroId) $consulta->where('id', $id)->update($valores);
            else DB::table('recepciones.contenido_recepcion')->insert(['id' => $id, 'created_at' => now(), ...$valores]);
            $this->reset('registroId', 'identificador', 'cantidad', 'scientificName', 'recordNumber', 'preparations', 'observaciones', 'contenidoCompleto');
        });
    }

    public function editarRegistro(string $id): void
    {
        $this->solicitud();
        $fila = DB::table('recepciones.contenido_recepcion')->where('solicitud_id', $this->id)->where('id', $id)->first();
        abort_unless($fila, 404);
        $datos = json_decode($fila->datos_dwc, true, flags: JSON_THROW_ON_ERROR);
        $this->registroId = $id; $this->identificador = $fila->identificador; $this->cantidad = $fila->cantidad;
        $this->scientificName = $datos['scientificName']; $this->recordNumber = $datos['recordNumber'];
        $this->preparations = $datos['preparations']; $this->observaciones = $fila->observaciones ?? '';
        $this->contenidoCompleto = false;
    }

    public function eliminarRegistro(string $id): void
    {
        DB::transaction(function () use ($id): void {
            abort_if($this->solicitud(true)->contenido_validado_en, 409);
            DB::table('recepciones.contenido_recepcion')->where('solicitud_id', $this->id)->where('id', $id)->delete();
            $this->contenidoCompleto = false;
        });
    }

    public function finalizar(AprobarRecepcionLoteHandler $aprobar): void
    {
        $this->validate(['contenidoCompleto' => 'accepted']);
        foreach (array_keys(ItemChecklistRecepcion::cases()) as $indice) {
            if (($this->criterios[$indice] ?? false) !== true) {
                $this->addError('criterios', 'Comprueba todos los criterios de recepción.');
                return;
            }
        }
        DB::transaction(function () use ($aprobar): void {
            $s = $this->solicitud(true);
            if ($s->contenido_validado_en) return;
            $filas = DB::table('recepciones.contenido_recepcion')->where('solicitud_id', $this->id);
            if (!$filas->exists()) {
                $this->addError('contenidoCompleto', 'Registra y valida el contenido recibido antes de finalizar.');
                return;
            }
            $s->forceFill(['contenido_validado_en' => now(), 'contenido_validado_por' => auth()->id()])->save();
            $aprobar(new AprobarRecepcionLoteInput($this->id, (string) auth()->id(), array_map(
                fn (ItemChecklistRecepcion $item): array => ['item' => $item->value, 'resultado' => $item->resultadoConforme()], ItemChecklistRecepcion::cases(),
            )));
        });
    }

    public function render()
    {
        return view('gestionprestamosrecepciones::curador.contenido-recepcion', [
            'solicitud' => $this->solicitud(),
            'filas' => DB::table('recepciones.contenido_recepcion')->where('solicitud_id', $this->id)->orderBy('created_at')->get(),
            'listaCriterios' => ItemChecklistRecepcion::cases(),
        ]);
    }
}
