<?php

namespace App\Livewire;

use App\Enums\RolUsuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EspecimenEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\LocalidadEloquentModel;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\TaxonEloquentModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Url]
    public string $periodoAnalisis = '12';

    public string $archivoSeleccion = '';

    public function updatedPeriodoAnalisis(): void
    {
        if (! in_array($this->periodoAnalisis, ['6', '12', '24'], true)) {
            $this->periodoAnalisis = '12';
        }
    }

    public function descargarReporteDepositos(): StreamedResponse
    {
        abort_unless(auth()->user()?->tieneAlgunRol(RolUsuario::CURADOR, RolUsuario::ADMIN), 403);

        $inicio = $this->inicioAnalisis();

        return response()->streamDownload(function () use ($inicio): void {
            $salida = fopen('php://output', 'wb');
            // BOM UTF-8 para que Excel conserve tildes y nombres taxonómicos.
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Número', 'Trámite', 'Estado documental', 'Estado de recepción', 'Lotes', 'Individuos', 'Fecha de registro', 'Aprobación documental', 'Constatación física', 'Acta firmada'], ';');

            $filas = SolicitudDepositoEloquentModel::query()
                ->leftJoin('recepciones.recepcion_lotes as recepcion', 'recepcion.solicitud_deposito_id', '=', 'recepciones.solicitudes_deposito.id')
                ->where('recepciones.solicitudes_deposito.estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
                ->where('recepciones.solicitudes_deposito.created_at', '>=', $inicio)
                ->orderByDesc('recepciones.solicitudes_deposito.created_at')
                ->select([
                    'recepciones.solicitudes_deposito.numero',
                    'recepciones.solicitudes_deposito.tipo_tramite',
                    'recepciones.solicitudes_deposito.estado as estado_documental',
                    'recepciones.solicitudes_deposito.nro_lotes',
                    'recepciones.solicitudes_deposito.nro_individuos',
                    DB::raw("TO_CHAR(recepciones.solicitudes_deposito.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/Guayaquil', 'YYYY-MM-DD HH24:MI') as registrado_en"),
                    DB::raw("TO_CHAR(recepciones.solicitudes_deposito.aprobada_en AT TIME ZONE 'UTC' AT TIME ZONE 'America/Guayaquil', 'YYYY-MM-DD HH24:MI') as aprobada_en"),
                    'recepcion.estado as estado_recepcion',
                    DB::raw("TO_CHAR(recepcion.verificado_en AT TIME ZONE 'UTC' AT TIME ZONE 'America/Guayaquil', 'YYYY-MM-DD HH24:MI') as verificado_en"),
                    'recepcion.acta_firmada_ruta',
                ])
                ->cursor();

            foreach ($filas as $fila) {
                fputcsv($salida, [
                    $fila->numero,
                    $fila->tipo_tramite,
                    $fila->estado_documental,
                    $fila->estado_recepcion ?? 'Pendiente de entrega',
                    $fila->nro_lotes,
                    $fila->nro_individuos,
                    $fila->registrado_en,
                    $fila->aprobada_en,
                    $fila->verificado_en,
                    $fila->acta_firmada_ruta ? 'Sí' : 'No',
                ], ';');
            }

            fclose($salida);
        }, 'reporte-depositos-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render(): View
    {
        $user = Auth::user();

        return match ($user->rolActivo()) {
            RolUsuario::CURADOR, RolUsuario::ADMIN => view('livewire.dashboard.curador-panel', [
                ...$this->resumenCuraduria(),
                'graficoFamilias' => $this->graficoFamilias(),
                'graficoDeterminacion' => $this->graficoDeterminacion(),
                'graficoDepositosPorMes' => $this->graficoDepositosPorMes(),
                'graficoEstadosDepositos' => $this->graficoEstadosDepositos(),
                ...$this->analiticaDepositos(),
                ...$this->archivoCuratorial(),
            ]),
            RolUsuario::RECEPTOR => view('livewire.dashboard.receptor-panel', [
                'pendientesRecepcion' => SolicitudDepositoEloquentModel::query()
                    ->whereNotNull('codigo_qr')
                    ->whereNotIn('id', RecepcionLoteEloquentModel::query()->select('solicitud_deposito_id'))
                    ->count(),
                'lotesRecibidos' => RecepcionLoteEloquentModel::query()
                    ->whereIn('estado', ['Verificado Físicamente', 'Verificado con Observaciones'])
                    ->count(),
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

    /** Datos acotados para el visor del archivo, sin descargar objetos R2 al renderizar. */
    private function archivoCuratorial(): array
    {
        $imagenes = DB::table('divulgacion.imagenes_taxonomicas as imagen')
            ->leftJoin('taxonomia.especimenes as especimen', 'especimen.occurrence_id', '=', 'imagen.occurrence_id')
            ->leftJoin('taxonomia.taxones as taxon', 'taxon.id', '=', 'especimen.taxon_id')
            ->where('imagen.disco', 'r2')
            ->whereNotNull('imagen.ruta')
            ->orderByDesc('imagen.created_at')
            ->limit(16)
            ->get(['imagen.id', 'imagen.nombre_original', 'imagen.occurrence_id', 'taxon.nombre_cientifico']);

        $depositos = SolicitudDepositoEloquentModel::query()
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->orderByDesc('created_at')
            ->limit(16)
            ->get(['id', 'numero', 'estado', 'documentos_cargados', 'nombres_archivos_originales']);

        $elementos = [];
        foreach ($imagenes as $imagen) {
            $elementos[] = [
                'id' => 'imagen:'.$imagen->id,
                'tipo' => 'imagen',
                'titulo' => (string) ($imagen->nombre_cientifico ?: $imagen->nombre_original ?: $imagen->occurrence_id),
                'detalle' => (string) $imagen->occurrence_id,
                'url' => route('archivo.imagen', $imagen->id),
            ];
        }
        foreach ($depositos as $deposito) {
            $nombres = $deposito->nombres_archivos_originales ?? [];
            foreach (array_keys($deposito->documentos_cargados ?? []) as $indice => $clave) {
                $elementos[] = [
                    'id' => 'deposito:'.$deposito->id.':'.$indice,
                    'tipo' => 'documento',
                    'titulo' => (string) ($nombres[$clave] ?? $clave),
                    'detalle' => (string) $deposito->numero,
                    'url' => route('prestamos.deposito.documento', [$deposito->id, $indice]),
                    'expediente' => route('prestamos.curador.deposito.revisar', $deposito->id),
                ];
            }
        }

        $seleccion = collect($elementos)->firstWhere('id', $this->archivoSeleccion) ?? ($elementos[0] ?? null);

        return [
            'archivoElementos' => $elementos,
            'archivoActual' => $seleccion,
            'archivoDepositos' => $depositos,
        ];
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
            'colEspecimenes' => EspecimenEloquentModel::query()->count(),
            'colTaxones' => TaxonEloquentModel::query()->count(),
            'colLocalidades' => LocalidadEloquentModel::query()->count(),
            'colFamilias' => TaxonEloquentModel::query()->where('rango', 'familia')->count(),

            // Divulgación
            'divPublicados' => EspecimenDivulgableEloquentModel::query()->count(),

            // Recepción y validación
            'depPorRevisar' => (int) $depositos->por_revisar,
            'depEnviadas' => (int) $depositos->enviadas,
            'depLotesRecibidos' => RecepcionLoteEloquentModel::query()
                ->whereIn('estado', ['Verificado Físicamente', 'Verificado con Observaciones'])
                ->count(),
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
        $filas = DB::select(<<<'SQL'
            WITH RECURSIVE linaje AS (
                SELECT e.id AS especimen_id, t.id, t.padre_id, t.rango, t.nombre_cientifico
                FROM taxonomia.especimenes e
                INNER JOIN taxonomia.taxones t ON t.id = e.taxon_id
                UNION ALL
                SELECT linaje.especimen_id, padre.id, padre.padre_id, padre.rango, padre.nombre_cientifico
                FROM linaje
                INNER JOIN taxonomia.taxones padre ON padre.id = linaje.padre_id
            )
            SELECT nombre_cientifico AS etiqueta, COUNT(DISTINCT especimen_id) AS valor
            FROM linaje
            WHERE rango = 'familia'
            GROUP BY nombre_cientifico
            ORDER BY valor DESC, etiqueta ASC
            LIMIT 8
        SQL);

        return array_map(
            static fn (object $fila): array => ['etiqueta' => (string) $fila->etiqueta, 'valor' => (int) $fila->valor],
            $filas,
        );
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

    /** @return list<array{etiqueta: string, valor: int}> */
    private function graficoDepositosPorMes(): array
    {
        $cantidadMeses = (int) $this->periodoAnalisis;
        $inicioLocal = now('America/Guayaquil')->startOfMonth()->subMonths($cantidadMeses - 1);
        $inicio = $inicioLocal->utc();
        $conteos = SolicitudDepositoEloquentModel::query()
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->where('created_at', '>=', $inicio)
            ->selectRaw("TO_CHAR(created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/Guayaquil', 'YYYY-MM') AS periodo, COUNT(*) AS total")
            ->groupByRaw("TO_CHAR(created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/Guayaquil', 'YYYY-MM')")
            ->pluck('total', 'periodo');

        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $filas = [];

        for ($desplazamiento = 0; $desplazamiento < $cantidadMeses; $desplazamiento++) {
            $mes = $inicioLocal->addMonths($desplazamiento);
            $filas[] = [
                'etiqueta' => $meses[$mes->month - 1].' '.$mes->format('y'),
                'valor' => (int) ($conteos[$mes->format('Y-m')] ?? 0),
            ];
        }

        return $filas;
    }

    /** @return list<array{etiqueta: string, valor: int}> */
    private function graficoEstadosDepositos(): array
    {
        $inicio = $this->inicioAnalisis();
        $conteos = SolicitudDepositoEloquentModel::query()
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->where('created_at', '>=', $inicio)
            ->selectRaw('estado, COUNT(*) AS total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $estados = [
            EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value => 'Por revisar',
            EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value => 'En asesoría',
            EstadoSolicitudDeposito::RequiereCorreccion->value => 'Por corregir',
            EstadoSolicitudDeposito::AprobadaDocumentalmente->value => 'Aprobadas',
            EstadoSolicitudDeposito::Rechazada->value => 'Rechazadas',
            EstadoSolicitudDeposito::RechazoPermanente->value => 'Rechazo final',
            EstadoSolicitudDeposito::Devuelta->value => 'Devueltas',
        ];

        return collect($estados)
            ->map(fn (string $etiqueta, string $estado): array => [
                'etiqueta' => $etiqueta,
                'valor' => (int) ($conteos[$estado] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Indicadores de gestión museológica desde la llegada documental hasta el
     * cierre del acta de recepción. Todos se calculan sobre el período visible.
     *
     * @return array<string, mixed>
     */
    private function analiticaDepositos(): array
    {
        $inicio = $this->inicioAnalisis();
        $estadosConstatados = ['Verificado Físicamente', 'Verificado con Observaciones'];

        $solicitudes = SolicitudDepositoEloquentModel::query()
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value)
            ->where('created_at', '>=', $inicio);

        $resumenDocumental = (clone $solicitudes)->selectRaw(
            'COUNT(*) AS total,
             COUNT(*) FILTER (WHERE aprobada_en IS NOT NULL) AS aprobadas,
             COUNT(*) FILTER (WHERE estado = ?) AS por_revisar,
             COUNT(*) FILTER (WHERE estado = ?) AS por_corregir,
             COALESCE(AVG(EXTRACT(EPOCH FROM (aprobada_en - created_at)) / 86400) FILTER (WHERE aprobada_en IS NOT NULL), 0) AS dias_revision',
            [
                EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
                EstadoSolicitudDeposito::RequiereCorreccion->value,
            ],
        )->first();

        $recepciones = RecepcionLoteEloquentModel::query()
            ->where('created_at', '>=', $inicio)
            ->selectRaw(
                "COUNT(*) AS iniciadas,
                 COUNT(*) FILTER (WHERE estado IN (?, ?)) AS constatadas,
                 COUNT(*) FILTER (WHERE estado = 'Verificado con Observaciones') AS con_observaciones,
                 COUNT(*) FILTER (WHERE estado = 'Recepción Suspendida') AS suspendidas,
                 COUNT(*) FILTER (WHERE estado IN (?, ?) AND acta_firmada_ruta IS NULL) AS actas_pendientes,
                 COUNT(*) FILTER (WHERE acta_firmada_ruta IS NOT NULL) AS actas_firmadas,
                 COALESCE(AVG(EXTRACT(EPOCH FROM (verificado_en - created_at)) / 86400) FILTER (WHERE verificado_en IS NOT NULL), 0) AS dias_constatacion",
                [
                    ...$estadosConstatados,
                    ...$estadosConstatados,
                ],
            )->first();

        $estadosGrafico = $this->estadosRecepcionExcluyentes($inicio, $estadosConstatados);

        return [
            'periodoAnaliticoEtiqueta' => 'Últimos '.((int) $this->periodoAnalisis).' meses',
            'indicadoresDepositos' => [
                'total' => (int) $resumenDocumental->total,
                'aprobadas' => (int) $resumenDocumental->aprobadas,
                'porRevisar' => (int) $resumenDocumental->por_revisar,
                'porCorregir' => (int) $resumenDocumental->por_corregir,
                'diasRevision' => round((float) $resumenDocumental->dias_revision, 1),
                'constatadas' => (int) $recepciones->constatadas,
                'observaciones' => (int) $recepciones->con_observaciones,
                'suspendidas' => (int) $recepciones->suspendidas,
                'actasPendientes' => (int) $recepciones->actas_pendientes,
                'actasFirmadas' => (int) $recepciones->actas_firmadas,
                'diasConstatacion' => round((float) $recepciones->dias_constatacion, 1),
            ],
            'graficoRecepciones' => collect($estadosGrafico)
                ->map(fn (int $valor, string $etiqueta): array => ['etiqueta' => $etiqueta, 'valor' => max(0, $valor)])
                ->values()
                ->all(),
            'colaCuratorial' => $this->colaCuratorial($inicio, $estadosConstatados),
        ];
    }

    /** @return list<array{numero:string, detalle:string, estado:string, fecha:string, accion:string, ruta:string, prioridad:string}> */
    private function colaCuratorial(\DateTimeInterface $inicio, array $estadosConstatados): array
    {
        $documentales = SolicitudDepositoEloquentModel::query()
            ->whereIn('estado', [
                EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
                EstadoSolicitudDeposito::RequiereCorreccion->value,
                EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value,
            ])
            ->latest('updated_at')
            ->limit(8)
            ->get(['id', 'numero', 'estado', 'tipo_tramite', 'updated_at'])
            ->map(fn (SolicitudDepositoEloquentModel $solicitud): array => [
                'numero' => (string) $solicitud->numero,
                'detalle' => (string) $solicitud->tipo_tramite,
                'estado' => (string) $solicitud->estado,
                'fecha' => $solicitud->updated_at?->format('d/m/Y H:i') ?? '—',
                'accion' => 'Revisar expediente',
                'ruta' => route('prestamos.curador.deposito.revisar', $solicitud->id),
                'prioridad' => 'documental',
            ]);

        $actas = RecepcionLoteEloquentModel::query()
            ->join('recepciones.solicitudes_deposito as solicitud', 'solicitud.id', '=', 'recepciones.recepcion_lotes.solicitud_deposito_id')
            ->whereIn('recepciones.recepcion_lotes.estado', $estadosConstatados)
            ->whereNull('recepciones.recepcion_lotes.acta_firmada_ruta')
            ->latest('recepciones.recepcion_lotes.verificado_en')
            ->limit(8)
            ->get([
                'recepciones.recepcion_lotes.solicitud_deposito_id',
                'recepciones.recepcion_lotes.estado',
                'recepciones.recepcion_lotes.verificado_en',
                'solicitud.numero as numero_solicitud',
            ])
            ->map(function (RecepcionLoteEloquentModel $recepcion): array {
                return [
                    'numero' => (string) ($recepcion->numero_solicitud ?? 'Expediente'),
                    'detalle' => $recepcion->estado,
                    'estado' => 'Acta pendiente de firma',
                    'fecha' => $recepcion->verificado_en?->format('d/m/Y H:i') ?? '—',
                    'accion' => 'Generar y firmar',
                    'ruta' => route('prestamos.curador.deposito.acta', $recepcion->solicitud_deposito_id),
                    'prioridad' => 'acta',
                ];
            });

        return $actas->concat($documentales)
            ->sortByDesc(fn (array $fila): int => $fila['prioridad'] === 'acta' ? 2 : 1)
            ->take(10)
            ->values()
            ->all();
    }

    private function inicioAnalisis(): CarbonImmutable
    {
        $meses = in_array($this->periodoAnalisis, ['6', '12', '24'], true)
            ? (int) $this->periodoAnalisis
            : 12;

        return now('America/Guayaquil')
            ->startOfMonth()
            ->subMonths($meses - 1)
            ->utc();
    }

    /**
     * Estados excluyentes de la recepción para no contar un mismo lote en
     * constatación y nuevamente como acta firmada.
     *
     * @param  list<string>  $estadosConstatados
     * @return array<string, int>
     */
    private function estadosRecepcionExcluyentes(\DateTimeInterface $inicio, array $estadosConstatados): array
    {
        $conteos = RecepcionLoteEloquentModel::query()
            ->where('created_at', '>=', $inicio)
            ->selectRaw(
                "COUNT(*) FILTER (WHERE estado = 'En Verificación') AS en_verificacion,
                 COUNT(*) FILTER (WHERE estado = 'Recepción Suspendida') AS suspendidas,
                 COUNT(*) FILTER (WHERE estado = ? AND acta_firmada_ruta IS NULL) AS conforme_pendiente,
                 COUNT(*) FILTER (WHERE estado = ? AND acta_firmada_ruta IS NULL) AS observada_pendiente,
                 COUNT(*) FILTER (WHERE estado IN (?, ?) AND acta_firmada_ruta IS NOT NULL) AS actas_firmadas",
                [
                    $estadosConstatados[0],
                    $estadosConstatados[1],
                    $estadosConstatados[0],
                    $estadosConstatados[1],
                ],
            )
            ->first();

        return [
            'En verificación' => (int) $conteos->en_verificacion,
            'Constatados conformes · acta pendiente' => (int) $conteos->conforme_pendiente,
            'Constatados con observaciones · acta pendiente' => (int) $conteos->observada_pendiente,
            'Recepciones suspendidas' => (int) $conteos->suspendidas,
            'Actas firmadas' => (int) $conteos->actas_firmadas,
        ];
    }

    /** @return array<string, int> */
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
            'donacionesRealizadas' => $donacionesRealizadas,
            'depositosRecientes' => (clone $base)->orderByDesc('created_at')->limit(4)->get(['id', 'numero', 'estado', 'tipo_tramite', 'created_at']),
        ];
    }
}
