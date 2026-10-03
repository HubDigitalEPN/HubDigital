@props(['total' => 0, 'notas' => [], 'accion' => "cambiarVista('registros')"])
@if((int) $total > 0)
    <section class="mb-5 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-text-secondary" aria-label="Datos originales por revisar">
        <p><strong class="text-text-primary">Datos originales por revisar:</strong> {{ number_format((int) $total) }} {{ (int) $total === 1 ? 'registro conserva' : 'registros conservan' }} datos de clasificación pendientes de revisión. El material sigue disponible; las notas curatoriales no se presentan como taxones ni se suman como especies válidas.
        </p>
        @if($notas !== [])
            <details class="mt-2">
                <summary class="cursor-pointer font-medium text-science-blue">Consultar datos originales de la selección</summary>
                <ul class="mt-2 space-y-1">
                    @foreach(array_slice($notas, 0, 12) as $nota)
                        <li>
                            <span>{{ $nota['nota'] }}</span> · {{ number_format((int) $nota['total']) }} {{ (int) $nota['total'] === 1 ? 'registro' : 'registros' }}
                            @if(($nota['padre'] ?? '') === 'root')
                                · sin clasificación pública confirmada
                            @elseif(($nota['padre'] ?? '') !== '')
                                · contexto publicado: <em>{{ $nota['padre'] }}</em>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
        <button type="button" class="mt-2 underline text-science-blue" wire:click="{{ $accion }}" wire:loading.attr="disabled">Ver los registros de esta selección</button>
    </section>
@endif
