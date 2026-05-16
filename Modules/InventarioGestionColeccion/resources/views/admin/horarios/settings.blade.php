<div class="space-y-6 p-6">
    <flux:heading size="xl" level="1" class="text-blue-navy font-bold">Configuración de horario</flux:heading>

    @if($successMessage)
        <flux:callout variant="success" dismissible>
            {{ $successMessage }}
        </flux:callout>
    @endif

    @if($errorMessage)
        <flux:callout variant="danger" dismissible>
            {{ $errorMessage }}
        </flux:callout>
    @endif

    <div class="rounded-lg border border-border bg-surface shadow-sm p-6 space-y-4">
        <p class="text-sm text-text-primary">
            Define el horario de operación del sistema. Las cajas que entren o salgan fuera de este horario generarán alertas.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Hora de inicio</flux:label>
                <div class="flex items-center gap-2">
                    <flux:select
                        wire:model="horaInicio"
                        class="flex-1"
                    >
                        @for($i = 0; $i < 24; $i++)
                            <option value="{{ $i }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}:00</option>
                        @endfor
                    </flux:select>
                </div>
                <flux:error name="horaInicio" />
            </flux:field>

            <flux:field>
                <flux:label>Hora de fin</flux:label>
                <div class="flex items-center gap-2">
                    <flux:select
                        wire:model="horaFin"
                        class="flex-1"
                    >
                        @for($i = 1; $i <= 23; $i++)
                            <option value="{{ $i }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}:00</option>
                        @endfor
                    </flux:select>
                </div>
                <flux:error name="horaFin" />
            </flux:field>
        </div>

        <div class="pt-4">
            <flux:button
                wire:click="actualizar"
                class="w-full md:w-auto"
                variant="primary"
            >
                Guardar cambios
            </flux:button>
        </div>

        <div class="pt-4 border-t border-border">
            <div class="bg-bg-main p-4 rounded-lg">
                <p class="text-sm font-medium text-text-primary mb-2">Horario actual:</p>
                <p class="text-base text-text-primary">
                    <strong>{{ str_pad($horaInicio, 2, '0', STR_PAD_LEFT) }}:00</strong> a <strong>{{ str_pad($horaFin, 2, '0', STR_PAD_LEFT) }}:00</strong>
                </p>
            </div>
        </div>
    </div>
</div>
