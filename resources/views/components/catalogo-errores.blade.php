@props(['mensaje' => null])
@if($errors->any() || $mensaje)
    <div role="alert" class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900" data-catalogo-resumen-errores>
        <p class="font-semibold">La ficha no se guardó. Corrige los datos indicados.</p>
        @if($mensaje)<p class="mt-2">{{ $mensaje }}</p>@endif
        @if($errors->any())
            <ul class="mt-2 list-disc space-y-1 ps-5">
                @foreach($errors->messages() as $campo => $mensajes)
                    @foreach($mensajes as $texto)
                        <li><a href="#captura-{{ $campo }}" x-on:click.prevent="document.getElementById($el.getAttribute('href').slice(1))?.focus()" class="underline">{{ $texto }}</a></li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    </div>
@endif
