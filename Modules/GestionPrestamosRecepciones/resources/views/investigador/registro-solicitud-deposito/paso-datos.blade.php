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
            <flux:heading>El modelo de IA no está disponible</flux:heading>
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
                'N.º Permiso Recolección' => 'Copia de la autorización de recolección (MAE)',
                'N.º Permiso Movilización' => 'Copia del permiso de movilización',
                'Grupo Animal' => $tipoTramite === 'Donación'
                    ? 'Formato solicitud donación'
                    : 'Copia del permiso de movilización',
                'Provincia' => 'Copia del permiso de movilización',
                'Localidad' => 'Copia del permiso de movilización',
                'Origen Donación' => 'Carta de cesión de derechos / origen lícito',
                'N.º Individuos' => null,
                'N.º Morfoespecies' => null,
                'N.º Lotes' => null,
            ];

            $camposCuantitativos = ['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes'];

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
                    $esCuantitativo = in_array($campo, $camposCuantitativos);
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
                                :type="$esCuantitativo ? 'number' : 'text'"
                                :min="$esCuantitativo ? 0 : null"
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

    {{-- Validación de firma electrónica --}}
    @if(!empty($firmasElectronicas))
        <div class="space-y-3">
            <flux:heading size="sm" level="3">Firma electrónica</flux:heading>

            @php
                $sinFirma = array_filter($firmasElectronicas, fn ($estado) => $estado === 'sin_firma');
                $noVerificados = array_filter($firmasElectronicas, fn ($estado) => $estado === 'no_verificado');
                $todosFirmados = empty($sinFirma) && empty($noVerificados);
            @endphp

            @if($todosFirmados)
                <div class="rounded-lg border border-success/30 bg-success/5 p-4 flex items-center gap-3">
                    <flux:icon name="shield-check" class="size-5 text-success shrink-0" />
                    <div>
                        <p class="text-sm font-semibold text-text-primary">Todos los documentos están firmados electrónicamente</p>
                        <p class="text-xs text-text-secondary mt-0.5">Se verificó la firma digital de cada archivo cargado.</p>
                    </div>
                </div>
            @elseif(!empty($sinFirma) && empty($noVerificados))
                {{-- Documentos escaneados con firma manual: advertencia, no bloqueo --}}
                <div class="rounded-lg border border-warning/30 bg-warning/5 p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <flux:icon name="exclamation-triangle" class="size-5 text-warning shrink-0" />
                        <div>
                            <p class="text-sm font-semibold text-text-primary">{{ count($sinFirma) }} documento(s) sin firma electrónica digital</p>
                            <p class="text-xs text-text-secondary mt-0.5">Puedes continuar, pero el curador revisará los documentos antes de aprobar la solicitud.</p>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        @foreach($firmasElectronicas as $nombre => $estado)
                            <div class="flex items-center gap-2 text-sm">
                                @if($estado === 'firmado')
                                    <flux:icon name="check-circle" class="size-4 text-success shrink-0" />
                                    <span class="text-text-primary">{{ $nombre }}</span>
                                    <span class="text-xs text-success font-medium">Firmado</span>
                                @else
                                    <flux:icon name="exclamation-circle" class="size-4 text-warning shrink-0" />
                                    <span class="text-text-primary">{{ $nombre }}</span>
                                    <span class="text-xs text-warning font-medium">Sin firma digital</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- no_verificado: error real de pdfsig, bloquea --}}
                <div class="rounded-lg border border-error/30 bg-error/5 p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <flux:icon name="shield-exclamation" class="size-5 text-error shrink-0" />
                        <div>
                            <p class="text-sm font-semibold text-text-primary">No se pudo verificar la firma de {{ count($noVerificados) }} documento(s)</p>
                            <p class="text-xs text-text-secondary mt-0.5">El sistema no pudo comprobar la firma electrónica. Vuelve al paso anterior y carga nuevamente los documentos.</p>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        @foreach($firmasElectronicas as $nombre => $estado)
                            <div class="flex items-center gap-2 text-sm">
                                @if($estado === 'firmado')
                                    <flux:icon name="check-circle" class="size-4 text-success shrink-0" />
                                    <span class="text-text-primary">{{ $nombre }}</span>
                                    <span class="text-xs text-success font-medium">Firmado</span>
                                @elseif($estado === 'sin_firma')
                                    <flux:icon name="exclamation-circle" class="size-4 text-warning shrink-0" />
                                    <span class="text-text-primary">{{ $nombre }}</span>
                                    <span class="text-xs text-warning font-medium">Sin firma digital</span>
                                @else
                                    <flux:icon name="question-mark-circle" class="size-4 text-error shrink-0" />
                                    <span class="text-text-primary">{{ $nombre }}</span>
                                    <span class="text-xs text-error font-medium">No verificado</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="pt-1">
                        <flux:button variant="filled" size="sm" icon="arrow-left" wire:click="retroceder">
                            Volver a cargar documentos
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
    @endif

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
                <div class="flex flex-col gap-2 sm:flex-row sm:gap-3 sm:items-end">
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
                    <flux:button size="sm" variant="outline" wire:navigate href="{{ route('profile.edit') }}" icon="user">
                        Actualizar nombre en perfil
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
