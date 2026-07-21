<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoCuraduriaPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarJustificacionesAlertas\AceptarJustificacionesAlertasHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarJustificacionesAlertas\AceptarJustificacionesAlertasInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia\AprobarDonacionConTransferenciaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia\AprobarDonacionConTransferenciaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola\PriorizarSolicitudEnColaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola\PriorizarSolicitudEnColaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarDocumentalmenteSolicitud\RechazarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarDocumentalmenteSolicitud\RechazarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas\RechazarJustificacionesAlertasHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas\RechazarJustificacionesAlertasInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrioridadSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Componente Livewire para la revisión y decisión documental de una solicitud de
 * depósito o donación por parte del curador.
 */
#[Layout('layouts.app', params: ['title' => 'Revisar solicitud'])]
final class RevisarDeposito extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public string $nombreInvestigador = '';

    // ── Modal: confirmación de aprobación ────────────────────────────────────
    public bool $showConfirmacionModal = false;

    /** Acción de aprobación a confirmar: 'deposito' | 'donacion' | 'justificaciones'. */
    public string $accionAprobar = '';

    // ── Modal: rechazo documental ────────────────────────────────────────────
    public bool $showRechazoModal = false;

    #[Validate('required|in:Subsanable,Definitivo')]
    public string $tipoRechazo = 'Subsanable';

    #[Validate('required|string|min:10')]
    public string $motivoRechazo = '';

    // ── Modal: rechazo de justificaciones de alertas ─────────────────────────
    public bool $showRechazoJustificacionesModal = false;

    /** @var string[] Tipos de alerta cuya justificación se rechaza. */
    public array $justificacionesRechazadas = [];

    // ── Filtros de revisión de la matriz ─────────────────────────────────────

    /** Prioridad de columna a mostrar: 'todas'|'critica'|'recomendada'|'opcional'|'personalizado'. */
    public string $filtroPrioridadColumna = 'todas';

    /** @var string[] Columnas DwC elegidas a mano (solo se usan en modo 'personalizado'). */
    public array $columnasSeleccionadas = [];

    /** Despliega el selector de columnas a la carta. */
    public bool $mostrarSelectorColumnas = false;

    /** Estado de registro a mostrar: 'todos'|'pendiente'|'validado'|'corregido'|'revision'. */
    public string $filtroEstado = 'todos';

    /** Mapa token ASCII → valor real de EstadoRegistroEspecimen (evita comillas/acentos en la vista). */
    private const ESTADO_TOKENS = [
        'pendiente' => 'Pendiente',
        'validado' => 'Validado Técnicamente',
        'corregido' => 'Corregido por Sugerencia',
        'revision' => 'Validación Manual por Curaduría',
    ];

    /** Mostrar solo registros con advertencias de formato/completitud. */
    public bool $soloConAdvertencias = false;

    /** Búsqueda por nombre científico (original o corregido). */
    public string $busquedaMatriz = '';

    /**
     * Carga la solicitud y resuelve el nombre del investigador.
     */
    public function mount(string $id, UsuarioNombrePort $usuarios): void
    {
        $this->id = $id;

        $deposito = SolicitudDepositoEloquentModel::find($id);
        abort_if($deposito === null, 404);

        $this->nombreInvestigador = $usuarios->obtenerNombre($deposito->investigador_id)
            ?? $deposito->nombre_investigador_documento
            ?? $deposito->investigador_id;
    }

    /**
     * Abre el modal de confirmación para la acción de aprobación indicada.
     */
    public function pedirConfirmacion(string $accion): void
    {
        $this->accionAprobar = $accion;
        $this->showConfirmacionModal = true;
    }

    /**
     * Ejecuta la aprobación confirmada por el curador, según la acción seleccionada.
     */
    public function confirmarAprobacion(
        AprobarDocumentalmenteSolicitudHandler $aprobarHandler,
        AprobarDonacionConTransferenciaHandler $donacionHandler,
        AceptarJustificacionesAlertasHandler $justificacionesHandler,
    ): void {
        $this->showConfirmacionModal = false;

        match ($this->accionAprobar) {
            'deposito' => $this->aprobar($aprobarHandler),
            'donacion' => $this->aprobarDonacion($donacionHandler),
            'justificaciones' => $this->aceptarJustificaciones($justificacionesHandler),
            default => null,
        };
    }

    /**
     * Aprueba documentalmente un depósito sin alertas pendientes.
     */
    public function aprobar(AprobarDocumentalmenteSolicitudHandler $handler): void
    {
        ($handler)(new AprobarDocumentalmenteSolicitudInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->dispatch('toast', message: 'Solicitud aprobada documentalmente. Código QR asignado.');
    }

    /**
     * Aprueba una donación generando el Acta de Transferencia de Dominio.
     */
    public function aprobarDonacion(AprobarDonacionConTransferenciaHandler $handler): void
    {
        ($handler)(new AprobarDonacionConTransferenciaInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->dispatch('toast', message: 'Donación aprobada. Acta de transferencia y código QR generados.');
    }

    /**
     * Acepta todas las justificaciones de alertas pendientes y aprueba la solicitud.
     */
    public function aceptarJustificaciones(AceptarJustificacionesAlertasHandler $handler): void
    {
        ($handler)(new AceptarJustificacionesAlertasInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->dispatch('toast', message: 'Justificaciones aceptadas. Solicitud aprobada documentalmente.');
    }

    /**
     * Rechaza una o más justificaciones, dejando la solicitud en "Requiere Corrección".
     */
    public function rechazarJustificaciones(RechazarJustificacionesAlertasHandler $handler): void
    {
        $this->validate(
            ['justificacionesRechazadas' => 'required|array|min:1'],
            ['justificacionesRechazadas.required' => 'Selecciona al menos una justificación que no aceptas.'],
        );

        ($handler)(new RechazarJustificacionesAlertasInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            justificacionesRechazadas: array_values($this->justificacionesRechazadas),
        ));

        $this->redirectRoute('prestamos.curador.depositos', navigate: true);
    }

    /**
     * Rechaza documentalmente la solicitud indicando tipo (Subsanable/Definitivo) y motivo.
     */
    public function rechazar(RechazarDocumentalmenteSolicitudHandler $handler): void
    {
        $this->validate();

        ($handler)(new RechazarDocumentalmenteSolicitudInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            tipoRechazo: $this->tipoRechazo,
            motivo: $this->motivoRechazo,
        ));

        $this->redirectRoute('prestamos.curador.depositos', navigate: true);
    }

    /**
     * Mensajes de validación en español para el modal de rechazo documental
     * (el locale global es 'en', por eso se declaran aquí de forma explícita).
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'tipoRechazo.required' => 'Selecciona el tipo de rechazo.',
            'tipoRechazo.in' => 'El tipo de rechazo seleccionado no es válido.',
            'motivoRechazo.required' => 'Debes indicar el motivo del rechazo para el depositante.',
            'motivoRechazo.min' => 'El motivo del rechazo debe tener al menos 10 caracteres.',
        ];
    }

    /**
     * Clasifica la solicitud como prioritaria en la cola de revisión.
     */
    public function priorizar(PriorizarSolicitudEnColaHandler $handler): void
    {
        ($handler)(new PriorizarSolicitudEnColaInput(
            solicitudId: $this->id,
            prioridad: PrioridadSolicitud::Prioritaria->value,
        ));

        $this->dispatch('toast', message: 'Solicitud marcada como prioritaria.');
    }

    /**
     * Aplica un preset de prioridad de columnas y cierra el selector a la carta.
     */
    public function seleccionarPrioridad(string $prioridad): void
    {
        $this->filtroPrioridadColumna = $prioridad;
        $this->mostrarSelectorColumnas = false;
    }

    /**
     * Abre el selector de columnas a la carta, pre-marcando las que se ven ahora
     * para que el curador parta de la vista actual y solo ajuste lo que necesita.
     */
    public function personalizarColumnas(MatrizEspeciesRepositoryInterface $matrizRepo, CatalogoCuraduriaPort $catalogo): void
    {
        $datos = $this->datosMatrizFiltrada($matrizRepo->buscarPorSolicitudId($this->id), $catalogo);

        $this->columnasSeleccionadas = array_values(array_filter(
            $datos['columnasVisibles'],
            fn (string $c) => $c !== 'scientificName',
        ));
        $this->filtroPrioridadColumna = 'personalizado';
        $this->mostrarSelectorColumnas = true;
    }

    /**
     * Cualquier cambio en la selección manual activa el modo personalizado.
     */
    public function updatedColumnasSeleccionadas(): void
    {
        $this->filtroPrioridadColumna = 'personalizado';
    }

    /**
     * Marca todas las columnas disponibles en el selector a la carta.
     */
    public function seleccionarTodasColumnas(MatrizEspeciesRepositoryInterface $matrizRepo): void
    {
        $this->columnasSeleccionadas = $this->columnasDwCDisponibles($matrizRepo);
        $this->filtroPrioridadColumna = 'personalizado';
    }

    /**
     * Desmarca todas las columnas (queda solo el nombre científico).
     */
    public function deseleccionarTodasColumnas(): void
    {
        $this->columnasSeleccionadas = [];
        $this->filtroPrioridadColumna = 'personalizado';
    }

    /**
     * Lista de columnas DwC de la matriz, excluyendo scientificName (siempre visible).
     *
     * @return string[]
     */
    private function columnasDwCDisponibles(MatrizEspeciesRepositoryInterface $matrizRepo): array
    {
        $matriz = $matrizRepo->buscarPorSolicitudId($this->id);
        $registros = $matriz !== null ? array_values($matriz->registros()) : [];
        $columnas = $registros !== [] ? array_keys($registros[0]->datosDwC()) : [];

        return array_values(array_filter($columnas, fn (string $c) => $c !== 'scientificName'));
    }

    /**
     * Recarga la solicitud, sus alertas y deriva el estado de la pantalla.
     */
    public function render(MatrizEspeciesRepositoryInterface $matrizRepo, CatalogoCuraduriaPort $catalogo): View
    {
        $deposito = SolicitudDepositoEloquentModel::with('alertas')->find($this->id);
        abort_if($deposito === null, 404);

        $alertas = $deposito->alertas;
        $hayAlertasPendientes = $alertas->contains('estado_revision', 'Pendiente de Revisión');
        $esDonacion = $deposito->tipo_tramite === TipoTramite::Donacion->value;
        $esPendiente = $deposito->estado === 'Pendiente de Revisión por Curaduría';

        $matriz = $matrizRepo->buscarPorSolicitudId($this->id);

        // Hallazgos taxonómicos que quedarían pendientes de validación manual si se aprueba.
        $hallazgosMatriz = 0;
        if ($matriz !== null) {
            foreach ($matriz->registros() as $registro) {
                if ($registro->estado()->value === 'Validación Manual por Curaduría') {
                    $hallazgosMatriz++;
                }
            }
        }

        $vista = [
            'deposito' => $deposito,
            'alertas' => $alertas,
            'hayAlertasPendientes' => $hayAlertasPendientes,
            'esDonacion' => $esDonacion,
            'esPendiente' => $esPendiente,
            'matriz' => $matriz,
            'hallazgosMatriz' => $hallazgosMatriz,
        ];

        return view(
            'gestionprestamosrecepciones::curador.revisar-deposito',
            $vista + $this->datosMatrizFiltrada($matriz, $catalogo),
        );
    }

    /**
     * Aplica los filtros de revisión sobre la matriz y devuelve los datos que la
     * vista necesita: columnas visibles, registros filtrados y conteos para los chips.
     *
     * @return array<string, mixed>
     */
    private function datosMatrizFiltrada(?object $matriz, CatalogoCuraduriaPort $catalogo): array
    {
        $registros = $matriz !== null ? array_values($matriz->registros()) : [];
        $columnasDwC = $registros !== [] ? array_keys($registros[0]->datosDwC()) : [];

        $prioridadesCampos = $catalogo->prioridadesPorCampo($this->id);
        $prioridadDe = fn (string $col): string => $prioridadesCampos[$col] ?? 'opcional';

        // Columnas visibles según prioridad o selección manual; scientificName siempre presente.
        if ($this->filtroPrioridadColumna === 'personalizado') {
            $columnasVisibles = array_values(array_filter(
                $columnasDwC,
                fn (string $col) => in_array($col, $this->columnasSeleccionadas, true)
                    || $col === 'scientificName',
            ));
        } else {
            $columnasVisibles = array_values(array_filter(
                $columnasDwC,
                fn (string $col) => $this->filtroPrioridadColumna === 'todas'
                    || $prioridadDe($col) === $this->filtroPrioridadColumna
                    || $col === 'scientificName',
            ));
        }

        // scientificName va primero (identidad de la fila, queda fijo junto a Estado).
        if (in_array('scientificName', $columnasVisibles, true)) {
            $columnasVisibles = array_merge(
                ['scientificName'],
                array_values(array_filter($columnasVisibles, fn (string $c) => $c !== 'scientificName')),
            );
        }

        // Conteos de columnas por prioridad (sobre las columnas presentes en la matriz).
        $conteoPrioridades = ['critica' => 0, 'recomendada' => 0, 'opcional' => 0];
        foreach ($columnasDwC as $col) {
            $p = $prioridadDe($col);
            $conteoPrioridades[$p] = ($conteoPrioridades[$p] ?? 0) + 1;
        }

        // Conteos de filas por estado y por advertencias (sobre el total, sin filtrar).
        $conteoEstados = [];
        $conteoConAdvertencias = 0;
        foreach ($registros as $r) {
            $estado = $r->estado()->value;
            $conteoEstados[$estado] = ($conteoEstados[$estado] ?? 0) + 1;
            if ($this->registroTieneAdvertencias($r)) {
                $conteoConAdvertencias++;
            }
        }

        $busqueda = trim(mb_strtolower($this->busquedaMatriz));

        $estadoBuscado = self::ESTADO_TOKENS[$this->filtroEstado] ?? null;

        $registrosFiltrados = array_values(array_filter($registros, function ($r) use ($busqueda, $estadoBuscado): bool {
            if ($estadoBuscado !== null && $r->estado()->value !== $estadoBuscado) {
                return false;
            }
            if ($this->soloConAdvertencias && ! $this->registroTieneAdvertencias($r)) {
                return false;
            }
            if ($busqueda !== '') {
                $nombre = mb_strtolower($r->nombreCientifico().' '.(string) $r->nombreCorregido());
                if (! str_contains($nombre, $busqueda)) {
                    return false;
                }
            }

            return true;
        }));

        return [
            'columnasDwC' => $columnasDwC,
            'columnasVisibles' => $columnasVisibles,
            'prioridadesCampos' => $prioridadesCampos,
            'registrosFiltrados' => $registrosFiltrados,
            'conteoPrioridades' => $conteoPrioridades,
            'conteoEstados' => $conteoEstados,
            'conteoConAdvertencias' => $conteoConAdvertencias,
            'totalReg' => count($registros),
            'totalFiltrado' => count($registrosFiltrados),
        ];
    }

    /**
     * Un registro tiene advertencias si alguna de sus normalizaciones está marcada como inválida.
     */
    private function registroTieneAdvertencias(object $registro): bool
    {
        foreach ($registro->normalizaciones() as $n) {
            if (! empty($n['invalido'])) {
                return true;
            }
        }

        return false;
    }
}
