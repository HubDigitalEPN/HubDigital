@props(['foto', 'clave', 'marcar' => true])
@php
    $encuadre = !$foto['ruta'] ? ($foto['retrato'] ?? null) : null;
    $estilo = '';
    if ($encuadre) {
        [$ancho, $alto, $x, $y, $w, $h] = $encuadre;
        $estilo = 'width:'.($ancho / $w * 100).'%;height:'.($alto / $h * 100).'%;left:'.(-$x / $w * 100).'%;top:'.(-$y / $h * 100).'%;';
    }
    $attributes = $marcar ? $attributes->merge(['data-lab-element' => 'foto-'.$clave, 'data-lab-kind' => 'foto', 'data-lab-label' => $foto['alt']]) : $attributes;
@endphp
<div {{ $attributes->class(['lab-portrait-frame']) }}>
    <x-laboratorio-foto :foto="$foto" :clave="$clave" :marcar="false" class="lab-portrait-image" :style="$estilo" />
</div>
