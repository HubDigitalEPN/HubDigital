<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\BuscarEspecimenesCatalogo\BuscarEspecimenesCatalogoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\BuscarEspecimenesCatalogo\BuscarEspecimenesCatalogoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud\ConsultarDetalleSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud\ConsultarDetalleSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoInput;

/**
 * Componente Livewire para el formulario de solicitud de préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Solicitud de préstamo'])]
final class SolicitudForm extends Component
{
    use HandlesDomainExceptions;

    public ?string $solicitudId = null;

    #[Validate('required|in:nacional,internacional')]
    public string $alcancePrestamo = 'nacional';

    #[Validate('required|string|max:200')]
    public string $tituloEstudio = '';

    #[Validate('required|string|max:255')]
    public string $institucionAdscripcion = '';

    #[Validate('required|string|max:255')]
    public string $lineaInvestigacion = '';

    #[Validate('required|string')]
    public string $propositoPrestamo = '';

    #[Validate('required|integer|min:1|max:24')]
    public int $duracionPropuestaMeses = 1;

    public string $justificacionExtendida = '';

    public string $estadoSolicitud = '';

    public string $comentarioCurador = '';

    /**
     * Los campos más allá de `especimen_id` y `cantidad_solicitada` son para
     * mostrar en pantalla: el servidor los resuelve de nuevo contra el catálogo
     * al guardar y no confía en los que llegan del navegador.
     *
     * @var list<array{especimen_id: ?string, especimen_codigo_externo: string, nombre_cientifico: ?string, disponibles: ?int, cantidad_solicitada: int}>
     */
    public array $items = [];

    /** Texto del buscador de especímenes del catálogo. */
    public string $busquedaEspecimen = '';

    public string $successMessage = '';

    public string $lastAutoSavedAt = '';

    /**
     * Inicializa el componente.
     *
     * @param ConsultarDetalleSolicitudHandler $detalleHandler
     * @param string|null $id
     * @return void
     */
    public function mount(ConsultarDetalleSolicitudHandler $detalleHandler, ?string $id = null): void
    {
        $this->solicitudId = $id;

        if ($id === null) {
            return;
        }

        $solicitud = $detalleHandler->handle(new ConsultarDetalleSolicitudInput($id));
        abort_if($solicitud === null, 404);
        abort_if($solicitud->investigadorId !== (string) auth()->id(), 403);

        $this->alcancePrestamo = $solicitud->alcancePrestamo ?? 'nacional';
        $this->tituloEstudio = $solicitud->tituloEstudio ?? '';
        $this->institucionAdscripcion = $solicitud->institucionAdscripcion ?? '';
        $this->lineaInvestigacion = $solicitud->lineaInvestigacion ?? '';
        $this->propositoPrestamo = $solicitud->propositoPrestamo ?? '';
        $this->duracionPropuestaMeses = $solicitud->duracionPropuestaMeses ?? 1;
        $this->justificacionExtendida = $solicitud->justificacionExtendida ?? '';
        $this->estadoSolicitud = $solicitud->estado;
        $this->comentarioCurador = $solicitud->comentarioCurador ?? '';
        $this->items = array_map(fn ($item) => [
            'especimen_id' => $item->especimenId,
            'especimen_codigo_externo' => (string) $item->codigoExterno,
            'nombre_cientifico' => $item->nombre,
            'disponibles' => $item->individualesDisponibles,
            'cantidad_solicitada' => (int) $item->cantidadSolicitada,
        ], $solicitud->items);
    }

    /**
     * Autoguarda el borrador de la solicitud.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoHandler $actualizar
     * @return void
     */
    public function autoGuardar(ActualizarSolicitudPrestamoHandler $actualizar): void
    {
        if ($this->solicitudId === null) {
            return;
        }

        $this->normalizeItems();

        try {
            $this->validate($this->reglas());
            $this->validarCantidades();
        } catch (ValidationException) {
            return;
        }

        try {
            $actualizar->handle(new ActualizarSolicitudPrestamoInput(
                solicitudId: $this->solicitudId,
                investigadorId: (string) auth()->id(),
                tituloEstudio: $this->tituloEstudio,
                institucionAdscripcion: $this->institucionAdscripcion,
                lineaInvestigacion: $this->lineaInvestigacion,
                propositoPrestamo: $this->propositoPrestamo,
                duracionPropuestaMeses: $this->duracionPropuestaMeses,
                items: $this->itemsParaInput(),
                justificacionExtendida: $this->duracionPropuestaMeses > 12 ? $this->justificacionExtendida : null,
            ));

            $this->lastAutoSavedAt = now()->format('H:i');
        } catch (\Throwable) {
            // fallo silencioso; el usuario puede guardar manualmente
        }
    }

    /**
     * Agrega a la solicitud un espécimen elegido del catálogo.
     *
     * La cantidad se prellena con los individuos disponibles, que además hacen
     * de tope. Si el inventario no registró el conteo no hay tope: se arranca en 1.
     */
    public function seleccionarEspecimen(
        BuscarEspecimenesCatalogoHandler $buscar,
        string $especimenId,
    ): void {
        foreach ($this->items as $item) {
            if ($item['especimen_id'] === $especimenId) {
                $this->busquedaEspecimen = '';

                return;
            }
        }

        $especimen = collect($buscar->handle(
            new BuscarEspecimenesCatalogoInput($this->busquedaEspecimen)
        )->resultados)->firstWhere('especimenId', $especimenId);

        if ($especimen === null) {
            $this->addError('busquedaEspecimen', 'Ese espécimen ya no está disponible.');

            return;
        }

        $this->items[] = [
            'especimen_id' => $especimen->especimenId,
            'especimen_codigo_externo' => $especimen->codigoCatalogo,
            'nombre_cientifico' => $especimen->nombreCientifico,
            'disponibles' => $especimen->individualesDisponibles,
            // Sin conteo conocido se arranca en 1: prellenar con null dejaba la
            // cantidad en 0 y la solicitud no pasaba la validación.
            'cantidad_solicitada' => $especimen->individualesDisponibles ?? 1,
        ];

        $this->busquedaEspecimen = '';
    }

    private function normalizeItems(): void
    {
        $this->items = array_values(array_map(fn (array $item) => [
            'especimen_id' => $item['especimen_id'] ?? null,
            'especimen_codigo_externo' => (string) ($item['especimen_codigo_externo'] ?? ''),
            'nombre_cientifico' => $item['nombre_cientifico'] ?? null,
            'disponibles' => isset($item['disponibles']) ? (int) $item['disponibles'] : null,
            'cantidad_solicitada' => (int) ($item['cantidad_solicitada'] ?? 1),
        ], $this->items));
    }

    /**
     * Reglas comunes a autoguardado, borrador y envío.
     *
     * @return array<string, string>
     */
    private function reglas(): array
    {
        return [
            'tituloEstudio' => 'required|string|max:200',
            'institucionAdscripcion' => 'required|string|max:255',
            'lineaInvestigacion' => 'required|string|max:255',
            'propositoPrestamo' => 'required|string',
            'duracionPropuestaMeses' => 'required|integer|min:1|max:24',
            'justificacionExtendida' => $this->duracionPropuestaMeses > 12 ? 'required|string|min:20' : 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.especimen_id' => 'required|uuid',
            'items.*.cantidad_solicitada' => 'required|integer|min:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'tituloEstudio.required' => 'El título del estudio es obligatorio.',
            'tituloEstudio.max' => 'El título no puede superar los 200 caracteres.',
            'institucionAdscripcion.required' => 'La institución de adscripción es obligatoria.',
            'lineaInvestigacion.required' => 'La línea de investigación es obligatoria.',
            'propositoPrestamo.required' => 'El propósito del préstamo es obligatorio.',
            'duracionPropuestaMeses.required' => 'La duración propuesta es obligatoria.',
            'duracionPropuestaMeses.integer' => 'La duración debe ser un número entero.',
            'duracionPropuestaMeses.min' => 'La duración mínima es 1 mes.',
            'duracionPropuestaMeses.max' => 'La duración máxima permitida es 24 meses.',
            'justificacionExtendida.required' => 'Debes justificar por qué la investigación requiere más de 12 meses.',
            'justificacionExtendida.min' => 'La justificación debe tener al menos 20 caracteres.',
            'items.required' => 'Debes agregar al menos un espécimen.',
            'items.min' => 'Debes agregar al menos un espécimen.',
            'items.*.especimen_id.required' => 'Selecciona el espécimen del catálogo.',
            'items.*.especimen_id.uuid' => 'Selecciona el espécimen del catálogo.',
            'items.*.cantidad_solicitada.required' => 'La cantidad es obligatoria.',
            'items.*.cantidad_solicitada.min' => 'La cantidad mínima es 1.',
        ];
    }

    /**
     * Verifica que ninguna cantidad supere los individuos disponibles.
     *
     * Va aparte porque el tope cambia por fila y las reglas de Laravel no
     * admiten un `max` distinto por índice del array. Es validación de
     * usabilidad: el servidor la repite contra el catálogo al guardar.
     */
    private function validarCantidades(): void
    {
        foreach ($this->items as $i => $item) {
            $disponibles = $item['disponibles'] ?? null;

            if ($disponibles !== null && $item['cantidad_solicitada'] > $disponibles) {
                throw ValidationException::withMessages([
                    "items.{$i}.cantidad_solicitada" => sprintf(
                        'Solo hay %d individuos disponibles de %s.',
                        $disponibles,
                        $item['especimen_codigo_externo'],
                    ),
                ]);
            }
        }
    }

    /**
     * Payload de ítems para los casos de uso: solo la referencia y la cantidad.
     *
     * @return list<array{especimen_id: string, cantidad_solicitada: int}>
     */
    private function itemsParaInput(): array
    {
        return array_map(fn (array $item) => [
            'especimen_id' => (string) $item['especimen_id'],
            'cantidad_solicitada' => $item['cantidad_solicitada'],
        ], $this->items);
    }

    /**
     * Elimina un ítem de la solicitud.
     *
     * @param int $index
     * @return void
     */
    public function removeItem(int $index): void
    {
        array_splice($this->items, $index, 1);
        $this->items = array_values($this->items);
    }

    /**
     * Guarda la solicitud como borrador.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoHandler $registrar
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoHandler $actualizar
     * @return void
     */
    public function guardarBorrador(
        RegistrarSolicitudPrestamoHandler $registrar,
        ActualizarSolicitudPrestamoHandler $actualizar,
    ): void {
        $this->normalizeItems();

        $this->validate($this->reglas(), $this->mensajes());
        $this->validarCantidades();

        $investigadorId = (string) auth()->id();

        if ($this->solicitudId === null) {
            $output = $registrar->handle(new RegistrarSolicitudPrestamoInput(
                investigadorId: $investigadorId,
                alcancePrestamo: $this->alcancePrestamo,
                tituloEstudio: $this->tituloEstudio,
                institucionAdscripcion: $this->institucionAdscripcion,
                lineaInvestigacion: $this->lineaInvestigacion,
                propositoPrestamo: $this->propositoPrestamo,
                duracionPropuestaMeses: $this->duracionPropuestaMeses,
                items: $this->itemsParaInput(),
                justificacionExtendida: $this->duracionPropuestaMeses > 12 ? $this->justificacionExtendida : null,
            ));

            $this->redirectRoute('prestamos.investigador.mis-solicitudes', navigate: true);

            return;
        }

        $actualizar->handle(new ActualizarSolicitudPrestamoInput(
            solicitudId: $this->solicitudId,
            investigadorId: $investigadorId,
            tituloEstudio: $this->tituloEstudio,
            institucionAdscripcion: $this->institucionAdscripcion,
            lineaInvestigacion: $this->lineaInvestigacion,
            propositoPrestamo: $this->propositoPrestamo,
            duracionPropuestaMeses: $this->duracionPropuestaMeses,
            items: $this->itemsParaInput(),
            justificacionExtendida: $this->duracionPropuestaMeses > 12 ? $this->justificacionExtendida : null,
        ));

        $this->successMessage = 'Borrador guardado correctamente.';
    }

    public function enviarSolicitud(
        ActualizarSolicitudPrestamoHandler $actualizar,
        EnviarSolicitudPrestamoHandler $enviar,
    ): void {
        $this->normalizeItems();

        if ($this->solicitudId === null) {
            $this->addError('solicitudId', 'Primero guarda el borrador antes de enviar.');

            return;
        }

        $this->validate($this->reglas(), $this->mensajes());
        $this->validarCantidades();

        $investigadorId = (string) auth()->id();

        $actualizar->handle(new ActualizarSolicitudPrestamoInput(
            solicitudId: $this->solicitudId,
            investigadorId: $investigadorId,
            tituloEstudio: $this->tituloEstudio,
            institucionAdscripcion: $this->institucionAdscripcion,
            lineaInvestigacion: $this->lineaInvestigacion,
            propositoPrestamo: $this->propositoPrestamo,
            duracionPropuestaMeses: $this->duracionPropuestaMeses,
            items: $this->itemsParaInput(),
            justificacionExtendida: $this->duracionPropuestaMeses > 12 ? $this->justificacionExtendida : null,
        ));

        $enviar->handle(new EnviarSolicitudPrestamoInput(
            solicitudId: $this->solicitudId,
            investigadorId: $investigadorId,
        ));

        $this->redirectRoute('prestamos.investigador.solicitud.detalle', ['id' => $this->solicitudId], navigate: true);
    }

    public function render(BuscarEspecimenesCatalogoHandler $buscar): View
    {
        $sugerencias = $this->busquedaEspecimen === ''
            ? []
            : $buscar->handle(new BuscarEspecimenesCatalogoInput($this->busquedaEspecimen))->resultados;

        return view('gestionprestamosrecepciones::investigador.solicitud-form', [
            'sugerencias' => $sugerencias,
        ]);
    }
}
