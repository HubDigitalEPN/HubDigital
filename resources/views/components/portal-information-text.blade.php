@props(['clave', 'variables' => []])
@php
    // El editor sólo guarda texto. Únicamente estos marcadores generan enlaces.
    $enlaces = [
        '{licencia}' => ['https://creativecommons.org/licenses/by/4.0/deed.es', 'CC BY 4.0'],
        '{licencia_completa}' => ['https://creativecommons.org/licenses/by/4.0/deed.es', 'Creative Commons Atribución 4.0 Internacional (CC BY 4.0)'],
        '{acuerdo_epn}' => ['https://atenea.epn.edu.ec/handle/25000/2104', 'Acuerdo de responsabilidad para el uso de medios y servicios electrónicos y tratamiento de datos personales de la EPN'],
        '{correo_datos}' => ['mailto:datos_personales@epn.edu.ec', 'datos_personales@epn.edu.ec'],
        '{politicas_epn}' => ['https://www.epn.edu.ec/administrativo/politicas-y-lineamientos-ti/', 'Políticas y lineamientos TI de la EPN'],
        '{sitio}' => ['https://labinvepn.org', 'https://labinvepn.org'],
    ];
    $partes = preg_split('/(\{[a-z_]+\})/u', \App\Support\PortalCopy::text($clave), -1, PREG_SPLIT_DELIM_CAPTURE);
@endphp
<p class="whitespace-pre-line">@foreach($partes as $parte)@if(isset($enlaces[$parte]))<a class="underline text-science-blue" href="{{ $enlaces[$parte][0] }}" @if(in_array($parte, ['{licencia}', '{licencia_completa}'], true)) rel="license" @endif>{{ $enlaces[$parte][1] }}</a>@else{{ $variables[$parte] ?? $parte }}@endif@endforeach</p>
