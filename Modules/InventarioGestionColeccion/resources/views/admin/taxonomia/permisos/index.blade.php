@php
    $tipoBadge = function (string $tipo): string {
        $clases = match ($tipo) {
            'research' => 'bg-blue-navy/10 text-blue-navy border-blue-navy/30',
            'transport' => 'bg-warning/10 text-warning border-warning',
            'export_import' => 'bg-bio-green/10 text-bio-green border-bio-green',
            default => 'bg-border/30 text-text-secondary border-border',
        };
        $label = match ($tipo) {
            'research' => 'Investigación',
            'transport' => 'Transporte',
            'export_import' => 'Exportación/Importación',
            default => $tipo,
        };

        return '<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold '.$clases.'">'.e($label).'</span>';
    };
@endphp

<div class="space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Permisos</flux:heading>
            <p class="text-sm text-text-secondary mt-1">
                Permisos legales del museo: investigación, transporte, exportación/importación.
            </p>
        </div>
        <flux:button icon="plus" variant="primary" wire:click="abrirCreate" class="w-full sm:w-auto">
            Nuevo permiso
        </flux:button>
    </div>

    @if($successMessage)<flux:callout variant="success" dismissible>{{ $successMessage }}</flux:callout>@endif
    @if($errorMessage && !$showCreateModal && !$showEditModal)<flux:callout variant="danger" dismissible>{{ $errorMessage }}</flux:callout>@endif

    <div class="rounded-lg border border-border bg-surface shadow-sm overflow-hidden">
        <div class="px-4 py-3 bg-bg-main border-b border-border">
            <span class="text-sm font-medium text-text-primary">{{ count($permisos) }} permiso(s)</span>
        </div>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-navy text-white">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Tipo</th>
                        <th class="px-4 py-3 text-left font-medium">Número</th>
                        <th class="px-4 py-3 text-left font-medium">Responsable</th>
                        <th class="px-4 py-3 text-left font-medium">Detalles</th>
                        <th class="px-4 py-3 text-left font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($permisos as $p)
                        <tr class="hover:bg-bg-main transition-colors align-top">
                            <td class="px-4 py-3">{!! $tipoBadge($p['tipo']) !!}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $p['numero'] ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $p['responsable'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs text-text-secondary max-w-md">{{ \Illuminate\Support\Str::limit($p['detalles'] ?? '', 150) ?: '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="abrirEdit('{{ $p['id'] }}')">
                                    Editar
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-text-secondary">No hay permisos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="md:hidden divide-y divide-border">
            @forelse($permisos as $p)
                <div class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        {!! $tipoBadge($p['tipo']) !!}
                        <flux:button variant="ghost" icon="pencil" wire:click="abrirEdit('{{ $p['id'] }}')">Editar</flux:button>
                    </div>
                    @if($p['numero'])
                        <div class="text-xs text-text-secondary">Número: <span class="font-mono text-text-primary">{{ $p['numero'] }}</span></div>
                    @endif
                    @if($p['responsable'])
                        <div class="text-xs text-text-secondary">Responsable: <span class="text-text-primary">{{ $p['responsable'] }}</span></div>
                    @endif
                    @if($p['detalles'])
                        <div class="text-xs text-text-secondary">{{ $p['detalles'] }}</div>
                    @endif
                </div>
            @empty
                <div class="p-6 text-center text-text-secondary text-sm">No hay permisos registrados.</div>
            @endforelse
        </div>
    </div>

    {{-- Modal: Crear --}}
    <flux:modal wire:model="showCreateModal" class="w-full max-w-2xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Nuevo permiso</flux:heading>
            @if($errorMessage)<flux:callout variant="danger">{{ $errorMessage }}</flux:callout>@endif
            <flux:field>
                <flux:label>Tipo</flux:label>
                <flux:select wire:model="tipo">
                    <option value="research">Investigación</option>
                    <option value="transport">Transporte</option>
                    <option value="export_import">Exportación/Importación</option>
                </flux:select>
                <flux:error name="tipo" />
            </flux:field>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Número</flux:label>
                    <flux:input wire:model="numero" placeholder="006-A-IC-DRSO-MA-2006" />
                    <flux:error name="numero" />
                </flux:field>
                <flux:field>
                    <flux:label>Responsable</flux:label>
                    <flux:input wire:model="responsable" placeholder="Nombre del responsable" />
                    <flux:error name="responsable" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>Detalles (opcional)</flux:label>
                <flux:textarea wire:model="detalles" rows="3" />
                <flux:error name="detalles" />
            </flux:field>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="registrar">Registrar</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: Editar --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-2xl">
        <div class="space-y-4 p-1">
            <flux:heading size="lg" class="text-text-primary">Editar permiso</flux:heading>
            @if($errorMessage)<flux:callout variant="danger">{{ $errorMessage }}</flux:callout>@endif
            <flux:field>
                <flux:label>Tipo</flux:label>
                <flux:select wire:model="editTipo">
                    <option value="research">Investigación</option>
                    <option value="transport">Transporte</option>
                    <option value="export_import">Exportación/Importación</option>
                </flux:select>
            </flux:field>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <flux:field><flux:label>Número</flux:label><flux:input wire:model="editNumero" /></flux:field>
                <flux:field><flux:label>Responsable</flux:label><flux:input wire:model="editResponsable" /></flux:field>
            </div>
            <flux:field>
                <flux:label>Detalles</flux:label>
                <flux:textarea wire:model="editDetalles" rows="3" />
            </flux:field>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Cancelar</flux:button>
                <flux:button variant="primary" wire:click="actualizar">Guardar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
