<form method="POST" action="{{ route('logout') }}" class="portal-logout">
    @csrf
    <button type="submit" {{ $attributes->class(['portal-logout-button']) }}>Cerrar sesión</button>
</form>
