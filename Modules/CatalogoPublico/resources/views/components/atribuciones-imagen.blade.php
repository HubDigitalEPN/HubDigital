@props(['datos' => [], 'dinamico' => false])
<section class="photo-attributions" aria-label="Atribuciones de la fotografía">
    <h3>Atribuciones</h3>
    <dl>
        @foreach(['photographer' => 'Photographer', 'license' => 'License', 'license_holder' => 'License holder', 'license_institution' => 'License institution', 'contact' => 'Contact'] as $campo => $etiqueta)
            <div><dt>{{ $etiqueta }}</dt><dd @if($dinamico) x-text="atribuciones[{{ \Illuminate\Support\Js::from($campo) }}] || 'No informado'" @endif>{{ $dinamico ? '' : (trim((string) ($datos[$campo] ?? '')) ?: 'No informado') }}</dd></div>
        @endforeach
    </dl>
</section>
