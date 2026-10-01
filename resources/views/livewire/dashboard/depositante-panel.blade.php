<div class="hub-transactional-ui hub-curator-home hub-depositor-home hub-workspace w-full p-4 sm:p-5">
    <x-banner-activar-rol />
    <header class="hub-curator-heading">
        <div>
            <h1>{{ \App\Support\WizardCopy::text('portal.titulo_depositante') }}</h1>
            <p>Depósitos de material biológico · Bienvenido, {{ auth()->user()->name }}</p>
        </div>
        <div class="hub-curator-heading__actions">
            <a href="{{ route('prestamos.investigador.deposito.crear') }}" wire:navigate class="hub-curator-button hub-curator-button--primary"><flux:icon name="plus" class="size-4" /> Nueva solicitud</a>
        </div>
    </header>

    <nav class="hub-curator-shortcuts hub-depositor-shortcuts" aria-label="Accesos principales">
        <a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><flux:icon name="archive-box" class="size-6" /><span><strong>Mis depósitos</strong><small>Solicitudes y documentos</small></span></a>
        <a href="{{ route('portal.catalogo', ['vista' => 'mapa']) }}"><flux:icon name="magnifying-glass" class="size-6" /><span><strong>Explorar colección</strong><small>Colección Biológica</small></span></a>
        <a href="{{ route('depositos.portal') }}"><flux:icon name="book-open" class="size-6" /><span><strong>Guía de depósitos</strong><small>Requisitos del trámite</small></span></a>
        <button type="button" x-on:click="$flux.modal('account-settings').show()"><flux:icon name="cog-6-tooth" class="size-6" /><span><strong>Configuración</strong><small>Cuenta y cambio de rol</small></span></button>
    </nav>

    <div class="hub-curator-grid">
        <section class="hub-curator-card hub-curator-card--main" aria-labelledby="mis-solicitudes-title">
            <div class="hub-curator-card__heading"><flux:icon name="document-text" class="size-6" /><div><h2 id="mis-solicitudes-title">Mis solicitudes</h2><p>Estado y movimientos de tus expedientes.</p></div><a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate>Ver todas <flux:icon name="arrow-right" class="size-4" /></a></div>
            <div class="hub-curator-metrics hub-curator-metrics--four">
                <a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ $totalEnviadas }}</strong><span>Enviadas</span></a>
                <a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ $pendientesRevision }}</strong><span>Por revisar</span></a>
                <a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ $pausadasAsesoria }}</strong><span>En asesoría</span></a>
                <a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ $rechazadas }}</strong><span>Rechazadas</span></a>
            </div>
            <div class="hub-curator-activity">
                <h3>Últimos movimientos</h3>
                @forelse($depositosRecientes as $deposito)
                    <a href="{{ route('prestamos.investigador.deposito.detalle', $deposito->id) }}" wire:navigate><span class="hub-curator-activity__dot"></span><span><strong>{{ $deposito->numero }}</strong> · {{ $deposito->tipo_tramite }}<small>{{ $deposito->estado }} · {{ $deposito->created_at?->format('d/m/Y') }}</small></span><flux:icon name="arrow-right" class="size-4" /></a>
                @empty
                    <p>Aún no tienes solicitudes enviadas.<small>Cuando envíes tu primer expediente, podrás seguirlo aquí.</small></p>
                @endforelse
            </div>
        </section>

        <section class="hub-curator-card hub-curator-card--main" aria-labelledby="aporte-title">
            <div class="hub-curator-card__heading"><flux:icon name="sparkles" class="size-6" /><div><h2 id="aporte-title">Tu contribución a la colección</h2><p>Material biológico aportado al museo.</p></div></div>
            @if($totalEnviadas > 0)
                <div class="hub-curator-metrics hub-curator-metrics--three hub-depositor-contribution">
                    <a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ number_format($individuos, 0, ',', '.') }}</strong><span>Individuos</span></a>
                    <a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ number_format($morfoespecies, 0, ',', '.') }}</strong><span>Morfoespecies</span></a>
                    <a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ number_format($lotes, 0, ',', '.') }}</strong><span>Lotes</span></a>
                </div>
                <p class="hub-depositor-impact">Tus aportes incluyen <strong>{{ $gruposTaxonomicos }}</strong> {{ $gruposTaxonomicos === 1 ? 'grupo taxonómico' : 'grupos taxonómicos' }} de <strong>{{ $provinciasOrigen }}</strong> {{ $provinciasOrigen === 1 ? 'provincia' : 'provincias' }} del Ecuador.</p>
            @else
                <div class="hub-curator-preview-empty"><flux:icon name="archive-box" class="size-9" /><strong>Tu colección empieza aquí.</strong><p>Al enviar un depósito o una donación, verás aquí el impacto de tus especímenes en el museo.</p></div>
            @endif
            <div class="hub-curator-pending">
                <div><flux:icon name="information-circle" class="size-5" /><h3>Cómo avanzar</h3></div>
                <p>Reúne los datos de procedencia y los documentos del material. El formulario guarda tu avance por etapas.</p>
            </div>
        </section>

        <section class="hub-curator-card hub-curator-card--summary" aria-labelledby="historial-title">
            <div class="hub-curator-card__heading"><flux:icon name="archive-box" class="size-6" /><div><h2 id="historial-title">Historial</h2><p>Consulta expedientes y archivos enviados.</p></div><a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate>Ir a mis depósitos <flux:icon name="arrow-right" class="size-4" /></a></div>
            <div class="hub-curator-metrics hub-curator-metrics--three"><a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ $totalEnviadas }}</strong><span>Solicitudes</span></a><a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ $donacionesRealizadas }}</strong><span>Donaciones</span></a><a href="{{ route('prestamos.investigador.mis-depositos') }}" wire:navigate><strong>{{ $pendientesRevision + $pausadasAsesoria }}</strong><span>En proceso</span></a></div>
        </section>
        <section class="hub-curator-card hub-curator-card--summary" aria-labelledby="ayuda-title">
            <div class="hub-curator-card__heading"><flux:icon name="question-mark-circle" class="size-6" /><div><h2 id="ayuda-title">Ayuda para el trámite</h2><p>Información sobre depósitos y donaciones.</p></div><a href="{{ route('depositos.portal') }}">Ver guía <flux:icon name="arrow-right" class="size-4" /></a></div>
            <p class="hub-depositor-help">Puedes presentar un depósito temporal o una donación definitiva. Selecciona el tipo al iniciar la solicitud.</p>
        </section>
    </div>
</div>
