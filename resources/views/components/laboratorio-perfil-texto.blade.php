@props(['persona', 'pagina', 'marcar' => true])
@php
    $nombre = $pagina['textos'][$persona.'_nombre'];
    $texto = trim($pagina['textos'][$persona.'_bio'].($persona === 'adrian' ? "\n\n".$pagina['textos']['adrian_especialidad'] : ''));
    $largo = preg_match_all('/\S+/u', $texto) > 40;
    $attributes = $marcar ? $attributes->merge(['data-lab-element' => 'texto-'.$persona.'_bio']) : $attributes;
@endphp
<div {{ $attributes->class(['lab-profile-summary']) }}>
    <p>{{ $largo ? \Illuminate\Support\Str::words($texto, 40, '…') : $texto }}</p>
    @if($largo)
        <button type="button" class="lab-profile-more" data-lab-open-profile="perfil-{{ $persona }}" aria-haspopup="dialog" aria-controls="perfil-{{ $persona }}" aria-label="Ver más sobre {{ $nombre }}">Ver más <span aria-hidden="true">↗</span></button>
    @endif
</div>
@if($largo)
    <dialog id="perfil-{{ $persona }}" class="lab-profile-dialog" data-lab-profile-dialog aria-labelledby="perfil-{{ $persona }}-titulo">
        <button type="button" class="lab-profile-close" data-lab-close-profile aria-label="Cerrar perfil de {{ $nombre }}" autofocus><span aria-hidden="true">×</span></button>
        <div class="lab-profile-dialog-content">
            <h2 id="perfil-{{ $persona }}-titulo">{{ $nombre }}</h2>
            @if(isset($pagina['textos'][$persona.'_cargo']))<p class="lab-role">{{ $pagina['textos'][$persona.'_cargo'] }}</p>@endif
            <div class="lab-profile-biography">
                <x-laboratorio-retrato :foto="$pagina['fotos'][$persona]" :clave="$persona" :marcar="false" />
                <p>{{ $texto }}</p>
            </div>
        </div>
    </dialog>
@endif
