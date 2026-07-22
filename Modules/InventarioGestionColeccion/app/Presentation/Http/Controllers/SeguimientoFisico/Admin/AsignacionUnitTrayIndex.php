<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray\ActualizarEspecimenesUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray\ActualizarEspecimenesUnitTrayInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete\ConsultarOcupacionGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete\ConsultarOcupacionGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearUnitTray\CrearUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearUnitTray\CrearUnitTrayInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarUnitTray\EliminarUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarUnitTray\EliminarUnitTrayInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables\ListarEspecimenesAsignablesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables\ListarEspecimenesAsignablesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja\ListarUnitTraysPorCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja\ListarUnitTraysPorCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarEspecimenes\ReubicarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarEspecimenes\ReubicarEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarUnitTray\ReubicarUnitTrayHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarUnitTray\ReubicarUnitTrayInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\EspecimenNoEncontradoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\GeneraSvgQr;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Pantalla del curador para organizar los especímenes en unit trays (bandejas) dentro de
 * cada caja: elige una caja, crea o selecciona un unit tray y le asigna especímenes
 * buscándolos de forma acotada. El catálogo de especímenes (48k+) nunca se carga de
 * golpe; solo se consulta al elegir un tray. Al asignar avisa, sin bloquear, qué
 * especímenes parecen fuera de lugar según su taxonomía. Es presentación pura: delega
 * cada acción en su caso de uso.
 */
#[Layout('layouts.app', params: ['title' => 'Asignación de unit trays'])]
final class AsignacionUnitTrayIndex extends Component
{
    use GeneraSvgQr;
    use TraduceErroresPersistencia;

    /** Valor del read-model `estado` para una caja en tránsito (contrato de ListarCajas). */
    private const ESTADO_TRANSITO = 'en_transito';

    public array $cajas = [];

    /** Gabinetes para el filtro del selector de cajas: [['id','codigo','nombre'], ...]. */
    public array $gabinetes = [];

    /** Filtro del selector de cajas: '' = todos, un id de gabinete, o 'transito'. */
    public string $filtroGabinete = '';

    public string $cajaSeleccionada = '';

    public string $cajaSeleccionadaLabel = '';

    public array $unitTrays = [];

    public string $unitTraySeleccionado = '';

    public array $especimenes = [];

    public array $especimenesSeleccionados = [];

    public string $busquedaEspecimen = '';

    public ?string $successMessage = null;

    public ?string $warningMessage = null;

    public ?string $errorMessage = null;

    // --- QR imprimible del unit tray (server-side, BaconQrCode, igual que taxonomía) ---
    public bool $modalQr = false;

    public ?string $qrTrayNumero = null;

    /** SVG inline del QR generado (codifica el UnitTrayId); null = modal cerrado. */
    public ?string $qrSvg = null;

    // --- QR de todos los unit trays de la caja seleccionada, para imprimir en lote ---
    public bool $modalQrCaja = false;

    /** @var array<int, array{numero: string, svg: string}> */
    public array $qrCajaTrays = [];

    // --- Flujo: reubicar especímenes a un unit tray de destino ---
    public bool $modalReubicarEspecimenes = false;

    /** @var array<int, array{id: string, codigoCatalogo: string, taxonNombre: string}> */
    public array $especimenesReubicar = [];

    /** Espécimen recién escaneado en espera de confirmación (popup); null = ninguno. */
    public ?array $especimenPorConfirmar = null;

    public string $trayDestinoReubicar = '';

    public ?string $trayDestinoReubicarLabel = null;

    /** True cuando la reubicación disparó la advertencia taxonómica y espera confirmación. */
    public bool $reubicacionRequiereConfirmacion = false;

    /** @var string[] Códigos de catálogo fuera de lugar de la última reubicación. */
    public array $reubicacionFueraDeLugar = [];

    // --- Flujo: reubicar un unit tray completo a otra caja ---
    public bool $modalReubicarTray = false;

    public string $trayAReubicar = '';

    public ?string $trayAReubicarLabel = null;

    /** Selección efímera de la caja de destino en el modal de mover tray. */
    public string $cajaDestinoSeleccionada = '';

    /** Carga solo las cajas disponibles; los especímenes se buscan después de forma acotada. */
    public function mount(ListarCajasHandler $cajasHandler): void
    {
        // Solo se cargan las cajas al montar. Los especímenes (catálogo de 48k+)
        // jamás se cargan de golpe: se buscan de forma acotada al elegir un tray.
        $this->cargarProtegido(function () use ($cajasHandler) {
            // Mapa caja → gabinete a partir de la ocupación de cada gabinete: permite filtrar el
            // selector de cajas por gabinete sin que el read-model de cajas conozca su ubicación.
            $gabineteDeCaja = $this->mapearCajasAGabinetes();

            $this->cajas = array_map(
                fn ($c) => [
                    'id' => $c->id,
                    'label' => "{$c->codigo}".($c->nombre ? " — {$c->nombre}" : ''),
                    'gabineteId' => $gabineteDeCaja[$c->id] ?? null,
                    'estado' => $c->estado,
                ],
                $cajasHandler->handle()->items,
            );
        });
    }

    /** Al cambiar el filtro de gabinete, si la caja seleccionada deja de ser visible, se descarta. */
    public function updatedFiltroGabinete(): void
    {
        $idsVisibles = array_column($this->cajasFiltradas(), 'id');
        if ($this->cajaSeleccionada !== '' && ! in_array($this->cajaSeleccionada, $idsVisibles, true)) {
            $this->cajaSeleccionada = '';
            $this->updatedCajaSeleccionada('');
        }
    }

    /** Al cambiar de caja reinicia la selección de tray/especímenes y carga los unit trays de esa caja. */
    public function updatedCajaSeleccionada(string $value): void
    {
        $this->unitTraySeleccionado = '';
        $this->especimenesSeleccionados = [];
        $this->cajaSeleccionadaLabel = $this->labelDeCaja($value);
        $this->limpiarMensajes();
        $this->cargarProtegido(fn () => $this->cargarUnitTrays($value));
    }

    /** Crea un unit tray en la caja seleccionada (el caso de uso lo numera automáticamente) y recarga la lista. */
    public function crearUnitTray(CrearUnitTrayHandler $handler): void
    {
        $this->validate(['cajaSeleccionada' => 'required|string']);

        try {
            $handler->handle(new CrearUnitTrayInput(cajaId: $this->cajaSeleccionada));
            $this->cargarUnitTrays($this->cajaSeleccionada);
            $this->flash('Unit tray creado y numerado automáticamente.');
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Selecciona un unit tray y carga los especímenes asignables, marcando como
     * preseleccionados los que ya pertenecen a ese tray para que el curador edite su
     * composición.
     */
    public function seleccionarUnitTray(string $unitTrayId): void
    {
        $this->unitTraySeleccionado = $unitTrayId;
        $this->busquedaEspecimen = '';
        $this->limpiarMensajes();
        $this->cargarProtegido(fn () => $this->cargarEspecimenes());
        $this->especimenesSeleccionados = array_values(array_map(
            fn ($e) => $e['id'],
            array_filter($this->especimenes, fn ($e) => $e['unitTrayId'] === $unitTrayId),
        ));
    }

    /** Reejecuta la búsqueda acotada de especímenes cada vez que cambia el texto de búsqueda. */
    public function updatedBusquedaEspecimen(): void
    {
        $this->cargarProtegido(fn () => $this->cargarEspecimenes());
    }

    /** Descarta la selección actual de tray y especímenes, volviendo al estado inicial de la caja. */
    public function cancelarSeleccion(): void
    {
        $this->unitTraySeleccionado = '';
        $this->especimenesSeleccionados = [];
        $this->especimenes = [];
        $this->busquedaEspecimen = '';
        $this->limpiarMensajes();
    }

    /**
     * Guarda la composición del unit tray con los especímenes seleccionados, recarga
     * lista y trays, confirma y, si el caso de uso detectó especímenes que no parecen
     * pertenecer al tray según su taxonomía, lo advierte sin bloquear la operación.
     */
    public function asignarEspecimenes(ActualizarEspecimenesUnitTrayHandler $handler): void
    {
        $this->validate(['unitTraySeleccionado' => 'required|string']);

        try {
            $output = $handler->handle(new ActualizarEspecimenesUnitTrayInput(
                unitTrayId: $this->unitTraySeleccionado,
                especimenIds: array_values($this->especimenesSeleccionados),
            ));
            $this->cargarEspecimenes();
            $this->cargarUnitTrays($this->cajaSeleccionada);
            $this->flash('Especímenes asignados al unit tray.');
            $this->advertirFueraDeLugar($output->especimenesFueraDeLugar);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /** Elimina un unit tray de la caja (recalcula la clasificación de la caja) y recarga la lista. */
    public function eliminarUnitTray(string $unitTrayId, EliminarUnitTrayHandler $handler): void
    {
        try {
            $handler->handle(new EliminarUnitTrayInput(unitTrayId: $unitTrayId));
            if ($this->unitTraySeleccionado === $unitTrayId) {
                $this->cancelarSeleccion();
            }
            $this->cargarUnitTrays($this->cajaSeleccionada);
            $this->flash('Unit tray eliminado.');
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Genera, server-side, el QR imprimible de un unit tray. El QR codifica el UnitTrayId, de
     * modo que es "siempre el mismo" para ese tray y sirve para escanearlo al reubicar.
     */
    public function mostrarQrTray(string $unitTrayId, string $numero): void
    {
        $this->limpiarMensajes();
        $this->qrTrayNumero = $numero;
        $this->qrSvg = $this->generarSvgQr($unitTrayId);
        $this->modalQr = true;
    }

    public function cerrarQrTray(): void
    {
        $this->modalQr = false;
        $this->qrTrayNumero = null;
        $this->qrSvg = null;
    }

    /**
     * Genera el QR de todos los unit trays de la caja seleccionada para imprimirlos de una
     * sola vez. Se ordenan por número (no por taxonomía, a diferencia del listado de arriba):
     * es el orden físico en que se pegan las etiquetas.
     */
    public function mostrarQrCaja(): void
    {
        $this->limpiarMensajes();
        $trays = $this->unitTrays;
        usort($trays, fn ($a, $b) => $a['numero'] <=> $b['numero']);
        $this->qrCajaTrays = array_map(
            fn ($t) => ['numero' => $t['numero'], 'svg' => $this->generarSvgQr($t['unitTrayId'])],
            $trays,
        );
        $this->modalQrCaja = true;
    }

    public function cerrarQrCaja(): void
    {
        $this->modalQrCaja = false;
        $this->qrCajaTrays = [];
    }

    // --- Reubicar especímenes ---

    public function abrirReubicarEspecimenes(): void
    {
        $this->reset(
            'especimenesReubicar',
            'especimenPorConfirmar',
            'trayDestinoReubicar',
            'trayDestinoReubicarLabel',
            'reubicacionRequiereConfirmacion',
            'reubicacionFueraDeLugar',
        );
        $this->limpiarMensajes();
        $this->modalReubicarEspecimenes = true;
    }

    public function cerrarReubicarEspecimenes(): void
    {
        $this->modalReubicarEspecimenes = false;
    }

    /**
     * Resuelve lo escaneado y lo deja en espera de confirmación (popup con la info resuelta).
     * El QR del espécimen es el mismo que genera la Estación de Etiquetado (Catálogo):
     * codifica la URL `/inventario/qr/{payload}` con un token opaco. También acepta un código
     * de catálogo tecleado a mano como respaldo.
     */
    public function escanearEspecimen(string $codigo): void
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return;
        }

        $match = $this->resolverEspecimenEscaneado($codigo);

        if ($match === null) {
            $this->errorMessage = "No se encontró el espécimen «{$codigo}».";

            return;
        }

        foreach ($this->especimenesReubicar as $e) {
            if ($e['id'] === $match['id']) {
                $this->errorMessage = "El espécimen «{$match['codigoCatalogo']}» ya está en la lista.";

                return;
            }
        }

        $this->errorMessage = null;
        $this->especimenPorConfirmar = [
            'id' => $match['id'],
            'codigoCatalogo' => $match['codigoCatalogo'],
            'taxonNombre' => $match['taxonNombre'],
        ];
    }

    /**
     * Si `$codigo` trae el payload del QR de taxonomía —envuelto en su URL, o pelado (etiquetas
     * antiguas o lectores que solo entregan el token)— lo resuelve por el mismo caso de uso que usa
     * la ficha pública del QR. Si no, se trata como código de catálogo tecleado a mano y se busca
     * con la búsqueda acotada de siempre (ILIKE por código o nombre científico, coincidencia exacta
     * de código).
     *
     * @return array{id: string, codigoCatalogo: string, taxonNombre: ?string}|null
     */
    private function resolverEspecimenEscaneado(string $codigo): ?array
    {
        $payload = $this->payloadDeQrTaxonomia($codigo);

        if ($payload !== null) {
            try {
                $output = app(ResolverCodigoQrHandler::class)->handle(new ResolverCodigoQrInput($payload));

                return [
                    'id' => $output->id,
                    'codigoCatalogo' => $output->codigoCatalogo,
                    'taxonNombre' => $output->taxonNombre,
                ];
            } catch (EspecimenNoEncontradoException|\InvalidArgumentException) {
                // Payload ilegible, corrupto o de otro tipo: se trata como "no encontrado", nunca como error fatal.
                return null;
            }
        }

        $items = app(ListarEspecimenesAsignablesHandler::class)
            ->handle(new ListarEspecimenesAsignablesInput(busqueda: $codigo, limite: 10))->items;

        foreach ($items as $it) {
            if ($it['codigoCatalogo'] === $codigo || $it['id'] === $codigo) {
                return $it;
            }
        }

        return null;
    }

    /**
     * Extrae el payload del QR de taxonomía: de la URL `/inventario/qr/{payload}` si el texto la
     * trae, o el propio texto si ya es un payload pelado (hex de 6+ caracteres, sin envoltorio de
     * URL). Null si no tiene pinta de payload (p. ej. un código de catálogo tecleado a mano).
     */
    private function payloadDeQrTaxonomia(string $texto): ?string
    {
        if (preg_match('#/inventario/qr/([0-9a-f]{6,})#i', $texto, $m) === 1) {
            return $m[1];
        }

        return preg_match('/^[0-9a-f]{6,}$/i', $texto) === 1 ? $texto : null;
    }

    public function confirmarEspecimenEscaneado(): void
    {
        if ($this->especimenPorConfirmar === null) {
            return;
        }
        $this->especimenesReubicar[] = $this->especimenPorConfirmar;
        $this->especimenPorConfirmar = null;
    }

    public function descartarEspecimenEscaneado(): void
    {
        $this->especimenPorConfirmar = null;
    }

    public function quitarEspecimenReubicar(string $id): void
    {
        $this->especimenesReubicar = array_values(
            array_filter($this->especimenesReubicar, fn ($e) => $e['id'] !== $id)
        );
    }

    /** Fija el unit tray de destino, escaneando su QR (UnitTrayId) o eligiéndolo de la lista. */
    public function fijarTrayDestino(string $unitTrayId): void
    {
        $unitTrayId = trim($unitTrayId);
        if ($unitTrayId === '') {
            return;
        }

        try {
            $tray = app(UnitTrayRepository::class)->buscarPorId(UnitTrayId::desde($unitTrayId));
        } catch (\InvalidArgumentException) {
            // El texto escaneado no tiene formato de UnitTrayId (p. ej. se escaneó el QR de un
            // espécimen en modo "destino"): se informa, nunca debe tumbar la página.
            $this->errorMessage = 'El código escaneado no es un QR de unit tray válido.';

            return;
        }

        if ($tray === null) {
            $this->errorMessage = "No se encontró el unit tray de destino «{$unitTrayId}».";

            return;
        }

        $caja = app(CajaRepository::class)->buscarPorId($tray->cajaId());
        $cajaLabel = $caja !== null
            ? (string) $caja->codigo().($caja->nombre() ? ' — '.$caja->nombre() : '')
            : 'caja desconocida';

        $this->errorMessage = null;
        $this->trayDestinoReubicar = $unitTrayId;
        $this->trayDestinoReubicarLabel = 'N.° '.$tray->numero()." ({$cajaLabel})";
    }

    /**
     * Ejecuta la reubicación de los especímenes acumulados al tray de destino. Si el caso de uso
     * pide confirmación (advertencia taxonómica), la expone para que el curador confirme o cancele.
     */
    public function reubicarEspecimenes(ReubicarEspecimenesHandler $handler, bool $confirmar = false): void
    {
        if ($this->especimenesReubicar === []) {
            $this->errorMessage = 'Escanea al menos un espécimen.';

            return;
        }
        if ($this->trayDestinoReubicar === '') {
            $this->errorMessage = 'Selecciona o escanea el unit tray de destino.';

            return;
        }

        try {
            $output = $handler->handle(new ReubicarEspecimenesInput(
                destinoUnitTrayId: $this->trayDestinoReubicar,
                especimenIds: array_column($this->especimenesReubicar, 'id'),
                confirmar: $confirmar,
            ));

            if ($output->requiereConfirmacion) {
                $this->reubicacionRequiereConfirmacion = true;
                $this->reubicacionFueraDeLugar = $output->especimenesFueraDeLugar;

                return;
            }

            $this->cerrarReubicarEspecimenes();
            $this->cargarUnitTrays($this->cajaSeleccionada);
            if ($this->unitTraySeleccionado !== '') {
                $this->cargarEspecimenes();
            }
            $this->flash('Especímenes reubicados al unit tray.');
            $this->advertirFueraDeLugar($output->especimenesFueraDeLugar);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    // --- Reubicar un unit tray completo a otra caja ---

    public function abrirReubicarTray(string $unitTrayId, string $numero): void
    {
        $this->limpiarMensajes();
        $this->trayAReubicar = $unitTrayId;
        $this->trayAReubicarLabel = 'N.° '.$numero;
        $this->cajaDestinoSeleccionada = '';
        $this->modalReubicarTray = true;
    }

    public function cerrarReubicarTray(): void
    {
        $this->modalReubicarTray = false;
        $this->trayAReubicar = '';
        $this->trayAReubicarLabel = null;
    }

    /** Reubica el tray a la caja elegida de la lista (id directo). */
    public function reubicarTrayACaja(string $cajaId, ReubicarUnitTrayHandler $handler): void
    {
        $this->ejecutarReubicacionTray($cajaId, $handler);
    }

    /** Reubica el tray a la caja resuelta por su RFID (escaneo NFC de la caja destino). */
    public function reubicarTrayPorRfid(string $rfid, ReubicarUnitTrayHandler $handler): void
    {
        $rfid = strtoupper(trim($rfid));
        if ($rfid === '') {
            return;
        }

        $caja = app(CajaRepository::class)->buscarPorCodigoRfid($rfid);
        if ($caja === null) {
            $this->errorMessage = "No hay ninguna caja con el RFID {$rfid}.";

            return;
        }

        $this->ejecutarReubicacionTray((string) $caja->id(), $handler);
    }

    private function ejecutarReubicacionTray(string $cajaDestinoId, ReubicarUnitTrayHandler $handler): void
    {
        if ($this->trayAReubicar === '' || $cajaDestinoId === '') {
            return;
        }

        try {
            $handler->handle(new ReubicarUnitTrayInput(
                unitTrayId: $this->trayAReubicar,
                cajaDestinoId: $cajaDestinoId,
            ));
            $this->cerrarReubicarTray();
            $this->cargarUnitTrays($this->cajaSeleccionada);
            $this->flash('Unit tray reubicado a otra caja.');
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.unit-trays.index', [
            'cajasFiltradas' => $this->cajasFiltradas(),
            'hayTransito' => $this->hayCajasEnTransito(),
        ]);
    }

    /**
     * Construye el mapa cajaId → gabineteId recorriendo la ocupación de cada gabinete. De paso
     * llena $this->gabinetes con las opciones del filtro. Las cajas que no aparecen en ninguna
     * ranura (p. ej. en tránsito) quedan fuera del mapa (gabineteId null).
     *
     * @return array<string, string>
     */
    private function mapearCajasAGabinetes(): array
    {
        $ocupacionHandler = app(ConsultarOcupacionGabineteHandler::class);
        $gabineteDeCaja = [];
        $this->gabinetes = [];

        foreach (app(ListarGabineteHandler::class)->handle()->items as $g) {
            $this->gabinetes[] = ['id' => $g->id, 'codigo' => $g->codigo, 'nombre' => $g->nombre];

            foreach ($ocupacionHandler->handle(new ConsultarOcupacionGabineteInput($g->id))->items as $ranura) {
                if ($ranura->ocupada && $ranura->cajaId) {
                    $gabineteDeCaja[$ranura->cajaId] = $g->id;
                }
            }
        }

        return $gabineteDeCaja;
    }

    /**
     * Cajas visibles según el filtro: '' = todas; 'transito' = solo las en tránsito; cualquier
     * otro valor = las alojadas en ese gabinete.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cajasFiltradas(): array
    {
        if ($this->filtroGabinete === '') {
            return $this->cajas;
        }

        if ($this->filtroGabinete === 'transito') {
            return array_values(array_filter($this->cajas, fn ($c) => $c['estado'] === self::ESTADO_TRANSITO));
        }

        return array_values(array_filter($this->cajas, fn ($c) => $c['gabineteId'] === $this->filtroGabinete));
    }

    /** ¿Hay alguna caja en tránsito? La opción del filtro solo se muestra si existen. */
    private function hayCajasEnTransito(): bool
    {
        foreach ($this->cajas as $c) {
            if ($c['estado'] === self::ESTADO_TRANSITO) {
                return true;
            }
        }

        return false;
    }

    /** Carga los unit trays de la caja indicada (lista vacía si no hay caja seleccionada). */
    private function cargarUnitTrays(string $cajaId): void
    {
        $this->unitTrays = $cajaId === ''
            ? []
            : app(ListarUnitTraysPorCajaHandler::class)
                ->handle(new ListarUnitTraysPorCajaInput($cajaId))->items;
    }

    /**
     * Busca los especímenes asignables al tray seleccionado de forma acotada por el texto
     * de búsqueda (nunca trae el catálogo completo). Sin tray seleccionado deja la lista vacía.
     */
    private function cargarEspecimenes(): void
    {
        if ($this->unitTraySeleccionado === '') {
            $this->especimenes = [];

            return;
        }

        $busqueda = trim($this->busquedaEspecimen);

        $this->especimenes = app(ListarEspecimenesAsignablesHandler::class)
            ->handle(new ListarEspecimenesAsignablesInput(
                busqueda: $busqueda !== '' ? $busqueda : null,
                unitTrayId: $this->unitTraySeleccionado,
            ))->items;
    }

    private function flash(string $mensaje): void
    {
        $this->successMessage = $mensaje;
        $this->errorMessage = null;
        $this->warningMessage = null;
    }

    private function limpiarMensajes(): void
    {
        $this->successMessage = null;
        $this->warningMessage = null;
        $this->errorMessage = null;
    }

    /**
     * Soft alert: avisa, sin bloquear, qué especímenes no parecen pertenecer al tray.
     *
     * @param  string[]  $codigos
     */
    private function advertirFueraDeLugar(array $codigos): void
    {
        if ($codigos === []) {
            return;
        }

        $lista = implode(', ', $codigos);
        $this->warningMessage = count($codigos) === 1
            ? "El especimen {$lista} no parece pertenecer a este unit tray según su taxonomía. Revisa su ubicación."
            : "Estos especímenes no parecen pertenecer a este unit tray según su taxonomía: {$lista}. Revisa su ubicación.";
    }

    private function labelDeCaja(string $cajaId): string
    {
        foreach ($this->cajas as $caja) {
            if ($caja['id'] === $cajaId) {
                return $caja['label'];
            }
        }

        return '';
    }
}
