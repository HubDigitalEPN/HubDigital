@props(['datos' => [], 'dinamico' => false])
<section class="photo-attributions" aria-label="Atribuciones de la fotografía">
    <h3>Atribuciones</h3>
    <dl>
        @foreach(['photographer' => 'Fotografía', 'license' => 'Licencia', 'license_holder' => 'Titular de la licencia', 'license_institution' => 'Institución', 'contact' => 'Contacto'] as $campo => $etiqueta)
            <div><dt>{{ $etiqueta }}</dt><dd @if($dinamico) x-text="atribuciones[{{ \Illuminate\Support\Js::from($campo) }}] || 'No informado'" @endif>{{ $dinamico ? '' : (trim((string) ($datos[$campo] ?? '')) ?: 'No informado') }}</dd></div>
        @endforeach
    </dl>
</section>
