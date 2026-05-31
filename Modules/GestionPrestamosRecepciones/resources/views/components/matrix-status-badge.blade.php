@props(['estado'])

@php
    $valor = $estado instanceof \Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies
        ? $estado->value
        : (string) $estado;

    $config = [
        'Validada Técnicamente' => ['bg' => 'bg-success/10', 'border' => 'border-success/30', 'text' => 'text-success'],
        'Cargada con Alertas' => ['bg' => 'bg-warning/10', 'border' => 'border-warning/30', 'text' => 'text-warning'],
        'Pendiente' => ['bg' => 'bg-bg-main', 'border' => 'border-border', 'text' => 'text-text-secondary'],
    ];

    $c = $config[$valor] ?? $config['Pendiente'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border whitespace-nowrap {$c['bg']} {$c['border']} {$c['text']}"]) }}>
    <span class="size-1.5 rounded-full bg-current"></span>
    {{ $valor }}
</span>
