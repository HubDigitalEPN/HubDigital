<div class="hub-transactional-ui hub-curator-home hub-depositor-home hub-workspace w-full p-4 sm:p-5">
    <x-banner-activar-rol />
    <header class="hub-curator-heading"><div><h1>Mis préstamos</h1><p>Consulta y custodia de especímenes · Bienvenido, {{ auth()->user()->name }}</p></div><div class="hub-curator-heading__actions"><a href="{{ route('prestamos.investigador.solicitud.crear') }}" wire:navigate class="hub-curator-button hub-curator-button--primary"><flux:icon name="plus" class="size-4" /> Nueva solicitud</a></div></header>
    <nav class="hub-curator-shortcuts hub-depositor-shortcuts" aria-label="Accesos principales">
        <a href="{{ route('prestamos.investigador.mis-solicitudes') }}" wire:navigate><flux:icon name="document-text" class="size-6" /><span><strong>Solicitudes</strong><small>Consulta y seguimiento</small></span></a>
        <a href="{{ route('prestamos.investigador.mis-actas') }}" wire:navigate><flux:icon name="document-check" class="size-6" /><span><strong>Mis actas</strong><small>Firma y documentos</small></span></a>
        <a href="{{ route('prestamos.investigador.mis-prestamos') }}" wire:navigate><flux:icon name="archive-box" class="size-6" /><span><strong>Préstamos</strong><small>Material bajo custodia</small></span></a>
        <a href="{{ route('portal.estadisticas') }}"><flux:icon name="magnifying-glass" class="size-6" /><span><strong>Explorar colección</strong><small>Mapa y estadísticas</small></span></a>
    </nav>
    <div class="hub-curator-grid">
        <section class="hub-curator-card hub-curator-card--main" aria-labelledby="solicitudes-title">
            <div class="hub-curator-card__heading"><flux:icon name="document-text" class="size-6" /><div><h2 id="solicitudes-title">Mis solicitudes</h2><p>Revisión de peticiones de préstamo.</p></div><a href="{{ route('prestamos.investigador.mis-solicitudes') }}" wire:navigate>Ver todas <flux:icon name="arrow-right" class="size-4" /></a></div>
            <div class="hub-curator-metrics hub-curator-metrics--three"><a href="{{ route('prestamos.investigador.mis-solicitudes') }}" wire:navigate><strong>{{ $statTotal }}</strong><span>Enviadas</span></a><a href="{{ route('prestamos.investigador.mis-solicitudes') }}" wire:navigate><strong>{{ $statAprobadas }}</strong><span>Aprobadas</span></a><a href="{{ route('prestamos.investigador.mis-solicitudes') }}" wire:navigate><strong>{{ $statPendientes }}</strong><span>En revisión</span></a></div>
            <div class="hub-curator-activity"><h3>Seguimiento</h3><p>{{ $statPendientes > 0 ? 'Tienes solicitudes esperando evaluación.' : 'No hay solicitudes pendientes de evaluación.' }}<small>Abre tus solicitudes para ver su estado y las observaciones del curador.</small></p></div>
        </section>
        <section class="hub-curator-card hub-curator-card--main" aria-labelledby="custodia-title">
            <div class="hub-curator-card__heading"><flux:icon name="archive-box" class="size-6" /><div><h2 id="custodia-title">Material bajo mi custodia</h2><p>Especímenes y préstamos activos.</p></div><a href="{{ route('prestamos.investigador.mis-prestamos') }}" wire:navigate>Ver préstamos <flux:icon name="arrow-right" class="size-4" /></a></div>
            <div class="hub-curator-metrics hub-curator-metrics--four"><a href="{{ route('prestamos.investigador.mis-prestamos') }}" wire:navigate><strong>{{ $especimenesEnCustodia }}</strong><span>Especímenes</span></a><a href="{{ route('prestamos.investigador.mis-prestamos') }}" wire:navigate><strong>{{ $prestActivos }}</strong><span>Activos</span></a><a href="{{ route('prestamos.investigador.mis-prestamos') }}" wire:navigate><strong>{{ $prestVencidos }}</strong><span>Vencidos</span></a><a href="{{ route('prestamos.investigador.mis-prestamos') }}" wire:navigate><strong>{{ $prestCerrados }}</strong><span>Devueltos</span></a></div>
            <div class="hub-curator-pending"><div><flux:icon name="clock" class="size-5" /><h3>Acciones pendientes</h3></div><p>{{ $prestVencidos > 0 ? 'Revisa la devolución del material vencido.' : 'No tienes devoluciones vencidas.' }}</p></div>
        </section>
    </div>
</div>
