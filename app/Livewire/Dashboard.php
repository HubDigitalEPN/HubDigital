<?php

namespace App\Livewire;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\EspecimenDivulgableEloquentModel;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ItemPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RegistroEspecimenEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas\ListarAlertasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas\ListarAlertasInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EspecimenEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EventoCicloIotEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\LocalidadEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\TaxonEloquentModel;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(
        ListarCajasHandler $cajasHandler,
        ListarAlertasHandler $alertasHandler,
    ): View {
        $user = Auth::user();

        return match ($user->rolActivo()) {
            RolUsuario::CURADOR => view('livewire.dashboard.curador-panel', [
                ...$this->resumenCuraduria(),
                ...$this->resumenSeguimientoFisico($cajasHandler, $alertasHandler),
                'graficoFamilias' => $this->graficoFamilias(),
                'graficoDeterminacion' => $this->graficoDeterminacion(),
            ]),
            RolUsuario::PRESTAMISTA => view(
                'livewire.dashboard.prestamista-panel',
                $this->estadisticasPrestamista((string) $user->id),
            ),
            RolUsuario::DEPOSITANTE => view(
                'livewire.dashboard.depositante-panel',
                $this->estadisticasDepositante((string) $user->id),
            ),
        };
    }

    /**
     * Panorama de la curaduría, agrupado por módulo funcional.
     *
     * El curador es el único rol que ve los cinco módulos, así que su dashboard
     * es el resumen de toda la operación y no solo de préstamos.
     *
     * @return array<string, int>
     */
    private function resumenCuraduria(): array
    {
        $borrador = EstadoSolicitudDeposito::EnBorrador->value;
        $porRevisar = EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value;

        $depositos = SolicitudDepositoEloquentModel::query()
            ->where('estado', '!=', $borrador)
            ->selectRaw(
                'COUNT(*) AS enviadas, COUNT(*) FILTER (WHERE estado = ?) AS por_revisar',
                [$porRevisar],
            )->first();

        $prestamos = PrestamoEloquentModel::query()
            ->selectRaw(
                "COUNT(*) FILTER (WHERE estado = 'activo') AS activos,
                 COUNT(*) FILTER (WHERE estado = 'vencido') AS vencidos",
            )->first();

        return [
            // Gestión de información taxonómica
            // enLaColeccion(): lo devuelto a su depositante ya no está en el museo, y
            // contarlo aquí infla la cifra que se cita en informes.
            'colEspecimenes' => EspecimenEloquentModel::query()->enLaColeccion()->count(),
            'colTaxones' => TaxonEloquentModel::query()->count(),
            'colLocalidades' => LocalidadEloquentModel::query()->count(),
            'colFamilias' => TaxonEloquentModel::query()->where('rango', 'familia')->count(),

            // Divulgación
            'divPublicados' => EspecimenDivulgableEloquentModel::query()->count(),

            // Recepción y validación
            'depPorRevisar' => (int) $depositos->por_revisar,
            'depEnviadas' => (int) $depositos->enviadas,
            'depLotesRecibidos' => RecepcionLoteEloquentModel::query()->count(),
            'depRegistrosMatriz' => RegistroEspecimenEloquentModel::query()->count(),

            // Préstamos
            'statSolicitudesPendientes' => SolicitudPrestamoModel::query()
                ->where('estado', 'enviada')
                ->count(),
            'statActasPorValidar' => ActaPrestamoModel::query()
                ->where('estado', 'pendiente_validacion')
                ->count(),
            'prestActivos' => (int) $prestamos->activos,
            'prestVencidos' => (int) $prestamos->vencidos,

            // Seguimiento físico (el resto llega por resumenSeguimientoFisico)
            'fisMovimientos' => EventoCicloIotEloquentModel::query()->count(),
        ];
    }

    /**
     * Las ocho familias con más especímenes.
     *
     * Deliberadamente NO se agrega una barra "Otras": la suma de la cola (más de
     * 3.000) sería la barra más larga y aplastaría a las familias que la gráfica
     * quiere comparar. El total de familias va en el subtítulo, que informa lo
     * mismo sin distorsionar la escala.
     *
     * @return list<array{etiqueta: string, valor: int}>
     */
    private function graficoFamilias(): array
    {
        return EspecimenEloquentModel::query()
            ->enLaColeccion()
            ->join('taxonomia.taxones as t', 't.id', '=', 'taxonomia.especimenes.taxon_id')
            ->where('t.rango', 'familia')
            ->selectRaw('t.nombre_cientifico AS etiqueta, COUNT(*) AS valor')
            ->groupBy('t.nombre_cientifico')
            ->orderByDesc('valor')
            ->limit(8)
            ->get()
            ->map(fn ($f) => ['etiqueta' => (string) $f->etiqueta, 'valor' => (int) $f->valor])
            ->all();
    }

    /**
     * Hasta qué nivel taxonómico está determinado cada espécimen.
     *
     * Se ordena por precisión biológica (no por cantidad): así la gráfica se lee
     * como una escala y muestra cuánto material sigue sin bajar a especie.
     *
     * @return list<array{etiqueta: string, valor: int}>
     */
    private function graficoDeterminacion(): array
    {
        $conteos = EspecimenEloquentModel::query()
            ->enLaColeccion()
            ->join('taxonomia.taxones as t', 't.id', '=', 'taxonomia.especimenes.taxon_id')
            ->selectRaw('t.rango AS rango, COUNT(*) AS valor')
            ->groupBy('t.rango')
            ->pluck('valor', 'rango');

        $escala = [
            'reino' => 'Reino',
            'phylum' => 'Filo',
            'clase' => 'Clase',
            'orden' => 'Orden',
            'suborden' => 'Suborden',
            'familia' => 'Familia',
            'subfamilia' => 'Subfamilia',
            'tribu' => 'Tribu',
            'genero' => 'Género',
            'especie' => 'Especie',
        ];

        $filas = [];
        foreach ($escala as $rango => $etiqueta) {
            $filas[] = ['etiqueta' => $etiqueta, 'valor' => (int) ($conteos[$rango] ?? 0)];
        }

        return $filas;
    }

    /**
     * Métricas del investigador solicitante.
     *
     * @return array<string, int>
     */
    private function estadisticasPrestamista(string $investigadorId): array
    {
        $solicitudes = SolicitudPrestamoModel::query()
            ->where('investigador_id', $investigadorId)
            ->selectRaw(
                "COUNT(*) AS total,
                 COUNT(*) FILTER (WHERE estado = 'aprobada') AS aprobadas,
                 COUNT(*) FILTER (WHERE estado IN ('enviada','observada')) AS pendientes",
            )->first();

        $prestamos = PrestamoEloquentModel::query()
            ->where('investigador_id', $investigadorId)
            ->selectRaw(
                "COUNT(*) FILTER (WHERE estado = 'activo') AS activos,
                 COUNT(*) FILTER (WHERE estado = 'vencido') AS vencidos,
                 COUNT(*) FILTER (WHERE estado LIKE 'cerrado%') AS cerrados",
            )->first();

        // Los ítems cuelgan de la solicitud, no del préstamo: la cadena es
        // items -> solicitud <- acta <- préstamo. Solo cuentan los préstamos
        // en los que el material sigue fuera de la colección.
        $especimenes = ItemPrestamoModel::query()
            ->whereIn(
                'solicitud_prestamo_id',
                ActaPrestamoModel::query()
                    ->whereIn(
                        'id',
                        PrestamoEloquentModel::query()
                            ->where('investigador_id', $investigadorId)
                            ->whereIn('estado', ['activo', 'vencido', 'en_transito'])
                            ->select('acta_prestamo_id'),
                    )
                    ->select('solicitud_prestamo_id'),
            )->sum('cantidad_solicitada');

        return [
            'statTotal' => (int) $solicitudes->total,
            'statAprobadas' => (int) $solicitudes->aprobadas,
            'statPendientes' => (int) $solicitudes->pendientes,
            'prestActivos' => (int) $prestamos->activos,
            'prestVencidos' => (int) $prestamos->vencidos,
            'prestCerrados' => (int) $prestamos->cerrados,
            'especimenesEnCustodia' => $especimenes,
        ];
    }

    /**
     * Métricas agregadas de los aportes del depositante para su dashboard.
     *
     * Mismo criterio que MisDepositos: solo solicitudes del investigador
     * autenticado que ya salieron de "En Borrador".
     *
     * @return array<string, mixed>
     */
    private function estadisticasDepositante(string $investigadorId): array
    {
        $base = SolicitudDepositoEloquentModel::query()
            ->where('investigador_id', $investigadorId)
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value);

        $pendiente = EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value;
        $pausada = EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value;
        $rechazada = EstadoSolicitudDeposito::Rechazada->value;

        // Una sola consulta para sumas, conteos por estado y diversidad.
        $resumen = (clone $base)->selectRaw(
            'COUNT(*) AS total_enviadas,
             COALESCE(SUM(nro_individuos), 0) AS individuos,
             COALESCE(SUM(nro_morfoespecies), 0) AS morfoespecies,
             COALESCE(SUM(nro_lotes), 0) AS lotes,
             COUNT(DISTINCT grupo_animal) AS grupos_taxonomicos,
             COUNT(DISTINCT provincia_origen) AS provincias_origen,
             COUNT(*) FILTER (WHERE estado = ?) AS pendientes_revision,
             COUNT(*) FILTER (WHERE estado = ?) AS pausadas_asesoria,
             COUNT(*) FILTER (WHERE estado = ?) AS rechazadas',
            [$pendiente, $pausada, $rechazada],
        )->first();

        $depositosAnio = (clone $base)
            ->where('tipo_tramite', TipoTramite::Deposito->value)
            ->whereYear('created_at', now()->year)
            ->count();

        $donacionesRealizadas = (clone $base)
            ->where('tipo_tramite', TipoTramite::Donacion->value)
            ->count();

        return [
            'totalEnviadas' => (int) $resumen->total_enviadas,
            'individuos' => (int) $resumen->individuos,
            'morfoespecies' => (int) $resumen->morfoespecies,
            'lotes' => (int) $resumen->lotes,
            'gruposTaxonomicos' => (int) $resumen->grupos_taxonomicos,
            'provinciasOrigen' => (int) $resumen->provincias_origen,
            'pendientesRevision' => (int) $resumen->pendientes_revision,
            'pausadasAsesoria' => (int) $resumen->pausadas_asesoria,
            'rechazadas' => (int) $resumen->rechazadas,
            'depositosAnio' => $depositosAnio,
            'cupoMaximoDepositos' => 3,
            'donacionesRealizadas' => $donacionesRealizadas,
        ];
    }

    /**
     * @return array{statCajasTotal: int, statCajasFueraDeLugar: int, statAlertasActivas: int}
     */
    private function resumenSeguimientoFisico(
        ListarCajasHandler $cajasHandler,
        ListarAlertasHandler $alertasHandler,
    ): array {
        $cajas = $cajasHandler->handle()->items;

        // Una caja está "fuera de su lugar" solo cuando no está alojada en una ranura.
        // en_gabinete, ubicacion_incorrecta y pendiente_clasificacion siguen presentes en
        // su ranura (banderas de negocio), por lo que NO cuentan como ausentes.
        $estadosFueraDeRanura = ['en_transito', 'extraccion_prolongada', 'extraviada'];

        return [
            'statCajasTotal' => count($cajas),
            'statCajasFueraDeLugar' => count(
                array_filter($cajas, fn ($c) => in_array($c->estado, $estadosFueraDeRanura, true)),
            ),
            'statAlertasActivas' => count(
                $alertasHandler->handle(new ListarAlertasInput('activa'))->items,
            ),
        ];
    }
}
