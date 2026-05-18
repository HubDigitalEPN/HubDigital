<div class="space-y-6">

    <div>
        <flux:heading size="lg" level="2" class="font-display">Resumen y validación de datos</flux:heading>
        <flux:text class="text-text-secondary text-sm mt-1">
            Revisa los datos extraídos automáticamente de la documentación y valida la identidad del solicitante.
        </flux:text>
    </div>

    {{-- Aviso cuando la extracción automática no pudo completarse --}}
    @if($advertenciaExtraccion === 'error_modelo')
        <flux:callout variant="warning" icon="cpu-chip">
            <flux:heading>El modelo de IA ({{ config('ai.providers.ollama.model') }}) no está disponible</flux:heading>
            <flux:text>
                No fue posible extraer los datos automáticamente porque el servicio de IA no respondió.
                Completa manualmente los campos marcados abajo. El flujo continúa con normalidad.
            </flux:text>
        </flux:callout>
    @elseif($advertenciaExtraccion === 'error_cola')
        <flux:callout variant="warning" icon="queue-list">
            <flux:heading>El procesador de tareas en segundo plano no está activo</flux:heading>
            <flux:text>
                La extracción automática no pudo iniciarse porque el worker de colas no está corriendo.
                Completa manualmente los campos marcados abajo. El flujo continúa con normalidad.
            </flux:text>
        </flux:callout>
    @endif

    {{-- Estado documental --}}
    @if($estadoDocumental === 'Requiere Corrección')
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading>Documentación requiere corrección</flux:heading>
            <flux:text>El Permiso de Movilización es obligatorio para la provincia de <strong>{{ $provincia }}</strong> pero no fue adjuntado. Regresa al paso anterior para cargarlo.</flux:text>
        </flux:callout>
    @endif

    {{-- Datos faltantes globales --}}
    <flux:error name="datosFaltantes" />

    @if(in_array('N.º Permiso Movilización', $datosFaltantes) && !isset($documentosCargados['Copia del permiso de movilización']))
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading>Se requiere el Permiso de Movilización</flux:heading>
            <flux:text>
                Los documentos indican que la recolección ocurrió fuera de Pichincha.
                Debes adjuntar la <strong>Copia del permiso de movilización</strong> para continuar.
            </flux:text>
            <flux:button size="sm" wire:click="retroceder" icon="arrow-left" class="mt-2">
                Volver a adjuntar documentos
            </flux:button>
        </flux:callout>
    @elseif(!empty($datosFaltantes))
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
                'N.º Permiso Recolección' => 'Copia de la autorización de recolección (MAATE)',
                'N.º Permiso Movilización' => 'Copia del permiso de movilización',
                'Grupo Animal' => $tipoTramite === 'Donación'
                    ? 'Formato solicitud donación'
                    : 'Copia del permiso de movilización',
                'Provincia' => 'Copia del permiso de movilización',
                'Localidad' => 'Copia del permiso de movilización',
                'Origen Donación' => 'Carta de cesión de derechos / origen lícito',
            ];

            $camposParaMostrar = array_keys($datosExtraidos);
        @endphp

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($camposParaMostrar as $campo)
                @php
                    $esFaltante = in_array($campo, $datosFaltantes);
                    $valor = $datosExtraidos[$campo] ?? null;
                    $fuente = $fuentesPorCampo[$campo] ?? null;
                    $clave = preg_replace('/[^a-zA-Z0-9]/', '_', $campo);
                    $estaEditando = isset($datosEnEdicion[$clave]);
                    $esManual = in_array($campo, $datosIngresadosManualmente);
                @endphp

                <x-gestionprestamosrecepciones::sum-cell
                    :campo="$campo"
                    :valor="$valor"
                    :fuente="$fuente"
                    :faltante="$esFaltante && !$estaEditando"
                    :manual="$esManual && !$esFaltante && !$estaEditando"
                >
                    @if($estaEditando)
                        <div class="flex gap-2 mt-1">
                            <flux:input
                                wire:model="datosEnEdicion.{{ $clave }}"
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
                        <flux:error name="datosEnEdicion.{{ $clave }}" />
                    @elseif($esFaltante)
                        <button
                            wire:click="iniciarEdicionDato('{{ $campo }}')"
                            class="mt-2 flex items-center gap-1 text-xs font-medium text-science-blue hover:text-science-blue/70 transition-colors cursor-pointer"
                        >
                            <flux:icon name="pencil-square" class="size-3" />
                            Capturar manualmente
                        </button>
                    @else
                        <button
                            wire:click="iniciarEdicionDato('{{ $campo }}')"
                            class="mt-2 flex items-center gap-1 text-xs text-text-secondary hover:text-text-primary transition-colors cursor-pointer"
                        >
                            <flux:icon name="pencil-square" class="size-3" />
                            Editar
                        </button>
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
            <div class="space-y-2">
                <div class="flex gap-3 items-end">
                    <flux:field class="flex-1 !mb-0">
                        <flux:label>Nombre tal como aparece en el formato de solicitud</flux:label>
                        <flux:input
                            wire:model="nombreEnDocumento"
                            placeholder="Ej. Juan Carlos Pérez Andrade"
                        />
                        <flux:error name="nombreEnDocumento" />
                    </flux:field>
                    <flux:button
                        variant="primary"
                        icon="shield-check"
                        wire:click="validarIdentidad"
                        wire:loading.attr="disabled"
                        wire:target="validarIdentidad"
                        class="shrink-0 bg-blue-navy hover:bg-blue-navy/90"
                    >
                        <flux:icon wire:loading wire:target="validarIdentidad" name="arrow-path" class="animate-spin size-4" />
                        Validar identidad
                    </flux:button>
                </div>
                <flux:text class="text-xs text-text-secondary">
                    Escribe el nombre exactamente como figura en el documento oficial cargado.
                </flux:text>
            </div>
        @endif

        {{-- Resultado de identidad --}}
        @if($resultadoIdentidad === 'Discrepancia (Tipográfica)')
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:heading>Discrepancia tipográfica detectada</flux:heading>
                <flux:text>Hay una diferencia menor entre tu nombre de perfil y el nombre del documento. Puedes continuar, pero se recomienda corregir el nombre en tu perfil de usuario.</flux:text>
                <flux:button size="sm" variant="outline" wire:navigate href="{{ route('profile.edit') }}" class="mt-2">
                    Corregir nombre en perfil
                </flux:button>
            </flux:callout>
        @elseif($resultadoIdentidad === 'Discrepancia (Tercero)')
            <flux:callout variant="danger" icon="x-circle">
                <flux:heading>Discrepancia significativa detectada</flux:heading>
                <flux:text>El nombre del documento y el del perfil difieren considerablemente. Elige una opción:</flux:text>
                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button size="sm" variant="ghost" wire:navigate href="{{ route('profile.edit') }}" icon="user">
                        Actualizar nombre en perfil
                    </flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="resetearValidacionIdentidad" icon="arrow-path">
                        Reingresar nombre del documento
                    </flux:button>
                </div>
                <flux:text class="mt-2 text-xs opacity-70">O adjunta la carta de delegación si gestionas el trámite a nombre de otra persona.</flux:text>
            </flux:callout>

            <x-gestionprestamosrecepciones::dropzone
                nombre="Carta de delegación / justificación de tercero"
                propiedad="archivoCartaDelegacion"
                :requerido="true"
                :cargado="isset($documentosCargados['Carta de delegación / justificación de tercero'])"
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
