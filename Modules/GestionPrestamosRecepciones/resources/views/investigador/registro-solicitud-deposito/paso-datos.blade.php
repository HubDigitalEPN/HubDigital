<div class="space-y-6">

    <div>
        <flux:heading size="lg" level="2">Resumen y Validación de Datos</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Revisa los datos extraídos automáticamente de la documentación y valida la identidad del solicitante.
        </flux:text>
    </div>

    {{-- Estado documental --}}
    @if($estadoDocumental === 'Requiere Corrección')
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading>Documentación requiere corrección</flux:heading>
            <flux:text>El Permiso de Movilización es obligatorio para la provincia de <strong>{{ $provincia }}</strong> pero no fue adjuntado. Regresa al paso anterior para cargarlo.</flux:text>
        </flux:callout>
    @endif

    {{-- Datos faltantes globales --}}
    <flux:error name="datosFaltantes" />

    @if(!empty($datosFaltantes))
        <flux:callout variant="danger" icon="x-circle">
            <flux:heading>{{ count($datosFaltantes) }} dato(s) requeridos no se pudieron extraer</flux:heading>
            <flux:text>La extracción automática no detectó: <strong>{{ implode(', ', $datosFaltantes) }}</strong>. Completa manualmente cada celda marcada abajo.</flux:text>
        </flux:callout>
    @endif

    {{-- Datos extraídos --}}
    <div class="space-y-3">
        <flux:heading size="sm" level="3">Datos integrados de documentación</flux:heading>

        @php
            $fuentesPorCampo = [
                'N.º Permiso Recolección' => 'Copia de la Autorización de Recolección (MAATE)',
                'N.º Permiso Movilización' => 'Copia del Permiso de Movilización',
                'Grupo Animal' => 'Copia del Permiso de Movilización',
                'Provincia' => 'Copia del Permiso de Movilización',
                'Localidad' => 'Copia del Permiso de Movilización',
            ];

            $camposParaMostrar = array_unique([
                ...array_keys($datosExtraidos),
                ...$datosFaltantes,
            ]);
        @endphp

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($camposParaMostrar as $campo)
                @php
                    $esFaltante = in_array($campo, $datosFaltantes);
                    $valor = $datosExtraidos[$campo] ?? null;
                    $fuente = $fuentesPorCampo[$campo] ?? null;
                    $estaEditando = isset($datosEnEdicion[$campo]);
                @endphp

                <x-gestionprestamosrecepciones::sum-cell
                    :campo="$campo"
                    :valor="$valor"
                    :fuente="$fuente"
                    :faltante="$esFaltante && !$estaEditando"
                >
                    @if($estaEditando)
                        <div class="flex gap-2 mt-1">
                            <flux:input
                                wire:model="datosEnEdicion.{{ $campo }}"
                                size="sm"
                                class="flex-1"
                                placeholder="Ingresa el valor…"
                            />
                            <flux:button
                                size="sm"
                                variant="primary"
                                wire:click="guardarDatoFaltante('{{ $campo }}')"
                                wire:loading.attr="disabled"
                                wire:target="guardarDatoFaltante('{{ $campo }}')"
                            >
                                <flux:icon wire:loading wire:target="guardarDatoFaltante('{{ $campo }}')" name="arrow-path" class="animate-spin size-3" />
                                Guardar
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="cancelarEdicionDato('{{ $campo }}')">
                                <flux:icon name="x-mark" class="size-3" />
                            </flux:button>
                        </div>
                        <flux:error name="datosEnEdicion.{{ $campo }}" />
                    @elseif($esFaltante)
                        <div class="mt-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="iniciarEdicionDato('{{ $campo }}')"
                                class="text-science-blue hover:text-science-blue/80"
                            >
                                <flux:icon name="pencil-square" class="size-3" />
                                Capturar manualmente
                            </flux:button>
                        </div>
                    @endif
                </x-gestionprestamosrecepciones::sum-cell>
            @endforeach
        </div>
    </div>

    {{-- Validación de identidad --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="sm" level="3">Validación de identidad</flux:heading>
            @if($resultadoIdentidad)
                <x-gestionprestamosrecepciones::deposito-status-badge estado="{{ $resultadoIdentidad }}" />
            @endif
        </div>

        <x-gestionprestamosrecepciones::identity-card
            :nombrePerfil="auth()->user()->name"
            :nombreEnDocumento="$nombreEnDocumento ?: null"
            :resultado="$resultadoIdentidad ?: null"
        />

        @if(!$resultadoIdentidad)
            <div class="flex gap-3 items-end">
                <flux:field class="flex-1">
                    <flux:label>Nombre tal como aparece en el Formato de Solicitud</flux:label>
                    <flux:input
                        wire:model="nombreEnDocumento"
                        placeholder="Ej. Juan Carlos Pérez Andrade"
                    />
                    <flux:error name="nombreEnDocumento" />
                    <flux:description>Escribe el nombre exactamente como figura en el documento oficial cargado.</flux:description>
                </flux:field>
                <flux:button
                    variant="filled"
                    wire:click="validarIdentidad"
                    wire:loading.attr="disabled"
                    wire:target="validarIdentidad"
                >
                    <flux:icon wire:loading wire:target="validarIdentidad" name="arrow-path" class="animate-spin size-4" />
                    <flux:icon wire:loading.remove wire:target="validarIdentidad" name="shield-check" class="size-4" />
                    Validar identidad
                </flux:button>
            </div>
        @endif

        {{-- Resultado de identidad --}}
        @if($resultadoIdentidad === 'Discrepancia (Tipográfica)')
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:heading>Discrepancia tipográfica detectada</flux:heading>
                <flux:text>Hay una diferencia menor entre tu nombre de perfil y el nombre del documento. Puedes continuar, pero se recomienda corregir el nombre en tu perfil de usuario.</flux:text>
                <flux:button size="sm" variant="ghost" wire:navigate href="{{ route('profile.edit') }}" class="mt-2">
                    Corregir nombre en perfil
                </flux:button>
            </flux:callout>
        @elseif($resultadoIdentidad === 'Discrepancia (Tercero)')
            <flux:callout variant="danger" icon="x-circle">
                <flux:heading>Discrepancia por tercero — Acción obligatoria</flux:heading>
                <flux:text>El nombre del documento difiere significativamente del usuario del sistema. Debes adjuntar una <strong>Carta de Delegación</strong> que autorice a tu nombre a gestionar este trámite.</flux:text>
            </flux:callout>

            <x-gestionprestamosrecepciones::dropzone
                nombre="Carta de Delegación / Justificación de Tercero"
                propiedad="archivoCartaDelegacion"
                :requerido="true"
                :cargado="isset($documentosCargados['Carta de Delegación / Justificación de Tercero'])"
            />
            <flux:error name="cartaDelegacion" />
        @elseif($resultadoIdentidad === 'Conforme')
            <flux:callout variant="success" icon="check-circle">
                <flux:text>Los nombres coinciden correctamente. Puedes continuar con el trámite.</flux:text>
            </flux:callout>
        @endif

        <flux:error name="identidad" />
    </div>

</div>
