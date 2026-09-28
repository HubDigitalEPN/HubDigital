<div class="space-y-3">
    @php
        $faltantesAviso = $datosFaltantes;
        if (trim($cargoConsultor) === '') $faltantesAviso[] = 'Cargo';
        if (trim($institucionConsultor) === '') $faltantesAviso[] = 'Institución';
    @endphp
    @if($faltantesAviso !== [])
        @php $avisoDatos = 'Completa los datos pendientes: '.implode(', ', $faltantesAviso).'.'; @endphp
        <div x-data x-init="$nextTick(() => $dispatch('show-toast', { message: @js($avisoDatos), variant: 'error' }))" class="hidden"></div>
    @endif

    <h2 class="text-sm font-semibold text-blue-navy">Datos de la solicitud</h2>

    <div class="overflow-hidden rounded-xl border border-science-blue/25 bg-surface shadow-sm">
        <div class="border-b border-science-blue/20 bg-science-blue/5 px-3 py-2">
            <p class="text-sm font-semibold text-blue-navy">Solicitante</p>
            <p class="text-xs text-text-secondary">{{ \App\Support\WizardCopy::text('datos.perfil') }}</p>
        </div>
        <dl class="grid gap-px bg-border sm:grid-cols-3">
            <div class="bg-white p-2.5">
                <dt class="flex items-center gap-1 text-xs font-semibold text-text-secondary">Nombre
                    <flux:tooltip content="Nombre de la persona que presenta la solicitud de depósito."><flux:icon name="information-circle" class="size-3 cursor-help" /></flux:tooltip>
                </dt>
                <dd class="text-sm text-text-primary">{{ auth()->user()->name }}</dd>
            </div>
            <div @class(['relative bg-white p-2.5', 'border border-error' => trim($cargoConsultor) === ''])>
                @if(trim($cargoConsultor) === '')<span class="absolute right-2.5 top-2.5 size-2 rounded-full bg-error" aria-label="Cargo faltante"></span>@endif
                <dt class="flex items-center gap-1 text-xs font-semibold text-text-secondary">Cargo
                    <flux:tooltip content="Función o puesto que desempeñas en la institución que presenta la solicitud."><flux:icon name="information-circle" class="size-3 cursor-help" /></flux:tooltip>
                </dt>
                <dd @class(['text-sm', 'italic text-error' => trim($cargoConsultor) === '', 'text-text-primary' => trim($cargoConsultor) !== ''])>{{ trim($cargoConsultor) === '' ? 'Falta ingresar' : $cargoConsultor }}</dd>
                <button type="button" wire:click="abrirEditorManual('Cargo')" class="mt-1 flex items-center gap-1 text-xs text-science-blue"><flux:icon name="pencil-square" class="size-3" />{{ trim($cargoConsultor) === '' ? 'Ingresar manualmente' : 'Editar' }}</button>
            </div>
            <div @class(['relative bg-white p-2.5', 'border border-error' => trim($institucionConsultor) === ''])>
                @if(trim($institucionConsultor) === '')<span class="absolute right-2.5 top-2.5 size-2 rounded-full bg-error" aria-label="Institución faltante"></span>@endif
                <dt class="flex items-center gap-1 text-xs font-semibold text-text-secondary">Institución
                    <flux:tooltip content="Entidad a la que perteneces y en cuyo nombre gestionas el depósito."><flux:icon name="information-circle" class="size-3 cursor-help" /></flux:tooltip>
                </dt>
                <dd @class(['text-sm', 'italic text-error' => trim($institucionConsultor) === '', 'text-text-primary' => trim($institucionConsultor) !== ''])>{{ trim($institucionConsultor) === '' ? 'Falta ingresar' : $institucionConsultor }}</dd>
                <button type="button" wire:click="abrirEditorManual('Institución')" class="mt-1 flex items-center gap-1 text-xs text-science-blue"><flux:icon name="pencil-square" class="size-3" />{{ trim($institucionConsultor) === '' ? 'Ingresar manualmente' : 'Editar' }}</button>
            </div>
        </dl>
    </div>
    <flux:error name="perfilConsultor" />

    {{-- Aviso cuando la extracción automática no pudo completarse --}}
    @if($advertenciaExtraccion === 'error_modelo')
        <flux:callout variant="warning" icon="cpu-chip">
            <flux:heading>La extracción automática no está disponible</flux:heading>
            <flux:text>
                El servicio de IA no respondió, así que los datos no se completaron solos.
                No te preocupes: puedes ingresarlos tú mismo y continuar sin problemas.
            </flux:text>
            <flux:button size="sm" variant="primary" icon="pencil-square" href="#datos-manuales" class="mt-3">
                Clic aquí para completar manualmente
            </flux:button>
        </flux:callout>
    @elseif($advertenciaExtraccion === 'error_cola')
        <flux:callout variant="warning" icon="queue-list">
            <flux:heading>La extracción automática no está disponible</flux:heading>
            <flux:text>
                El procesamiento en segundo plano no está activo, así que los datos no se completaron solos.
                No te preocupes: puedes ingresarlos tú mismo y continuar sin problemas.
            </flux:text>
            <flux:button size="sm" variant="primary" icon="pencil-square" href="#datos-manuales" class="mt-3">
                Clic aquí para completar manualmente
            </flux:button>
        </flux:callout>
    @endif

    {{-- Estado documental --}}
    @if($estadoDocumental === 'Requiere Corrección')
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:heading>Documentación requiere corrección</flux:heading>
            <flux:text>El Permiso de Movilización es obligatorio para la provincia de <strong>{{ $provincia }}</strong> pero no fue adjuntado. Regresa al paso anterior para cargarlo.</flux:text>
        </flux:callout>
    @endif

    <div id="datos-manuales" class="space-y-3 scroll-mt-6">
        <h3 class="text-sm font-semibold text-blue-navy">Material</h3>
        @php
            $fuentesPorCampo = [
                'N.º Permiso Recolección' => 'Copia de la autorización de recolección (MAE)',
                'N.º Permiso Movilización' => 'Copia del permiso de movilización',
                'N.º Investigación' => 'Documento de procedencia de los especimenes',
                'Grupo Animal' => $tipoTramite === 'Donación'
                    ? 'Formato solicitud donación'
                    : 'Copia del permiso de movilización',
                'Provincia' => 'Copia del permiso de movilización',
                'Administración Política' => 'Documento de procedencia de los especimenes',
                'Localidad' => 'Copia del permiso de movilización',
                'Origen Donación' => 'Carta de cesión de derechos / origen lícito',
                'N.º Individuos' => null,
                'N.º Morfoespecies' => null,
                'N.º Lotes' => null,
            ];

            $tooltipsPorCampo = [
                'N.º Permiso Recolección'  => 'Número del permiso emitido por el Ministerio del Ambiente y Agua (MAE/MAATE) que autoriza la recolección de los especímenes en campo.',
                'N.º Permiso Movilización' => 'Número de la guía de movilización emitida por el MAE que autoriza el traslado de los especímenes desde su lugar de recolección.',
                'N.º Investigación'        => 'Número o código del proyecto de investigación bajo el cual se obtuvieron los especímenes en el país de origen. Puede ser una referencia institucional, gubernamental o de la colección foránea.',
                'Grupo Animal'             => 'Referencia taxonómica del grupo de especímenes que se deposita. Puede indicarse a nivel de Familia, Género o Especie.',
                'Provincia'                => 'Provincia del Ecuador donde se realizó la recolección de los especímenes.',
                'Administración Política'  => 'División político-administrativa del lugar de recolección en el país de origen. Puede ser una provincia, departamento, estado u otra unidad administrativa equivalente.',
                'Localidad'                => 'Nombre específico del lugar donde se recolectaron los especímenes (ciudad, área natural, reserva, parroquia, etc.).',
                'Origen Donación'          => 'Descripción del origen de los especímenes que serán donados, confirmando la legalidad de su obtención.',
                'N.º Individuos'           => 'Número total de especímenes individuales incluidos en esta solicitud.',
                'N.º Morfoespecies'        => 'Número de morfoespecies distintas (grupos morfológicamente diferenciables) presentes en la colección.',
                'N.º Lotes'                => 'Número de lotes en que se agrupan los especímenes para su organización y registro en la colección.',
            ];

            $camposParaMostrar = array_keys($datosExtraidos);
        @endphp

        <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
            @foreach($camposParaMostrar as $campo)
                @php
                    $esFaltante = in_array($campo, $datosFaltantes);
                    $valor = $datosExtraidos[$campo] ?? null;
                    $fuente = $fuentesPorCampo[$campo] ?? null;
                    $esManual = in_array($campo, $datosIngresadosManualmente);
                @endphp

                <x-gestionprestamosrecepciones::sum-cell
                    :campo="$campo"
                    :valor="$valor"
                    :fuente="$fuente"
                    :faltante="$esFaltante"
                    :manual="$esManual && !$esFaltante"
                    :ayuda="$tooltipsPorCampo[$campo] ?? null"
                >
                    @if($esFaltante)
                        <button
                            type="button" wire:click="abrirEditorManual('{{ $campo }}')"
                            class="mt-2 flex items-center gap-1 text-xs font-medium text-science-blue hover:text-science-blue/70 transition-colors cursor-pointer"
                        >
                            <flux:icon name="pencil-square" class="size-3" />
                            Ingresar manualmente
                        </button>
                    @else
                        <button
                            type="button" wire:click="abrirEditorManual('{{ $campo }}')"
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

    <flux:modal name="editar-dato-deposito" class="w-full max-w-md">
        @if($campoEditorManual !== '')
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ $campoEditorManual }}</flux:heading>
                    <p class="mt-1 text-xs leading-relaxed text-text-secondary">
                        {{ match($campoEditorManual) {
                            'Cargo' => 'Indica el puesto o función que desempeñas en la institución solicitante.',
                            'Institución' => 'Selecciona la entidad a la que perteneces. El curador administra esta lista.',
                            'Provincia' => 'Selecciona la provincia donde se recolectó el material. Debe coincidir con la zona de recolección.',
                            'Localidad' => 'Indica el lugar de recolección dentro de la provincia seleccionada.',
                            default => $tooltipsPorCampo[$campoEditorManual] ?? 'Completa este dato de la solicitud de depósito.',
                        } }}
                    </p>
                </div>
                <div>
                    @if($campoEditorManual === 'Institución')
                        <select wire:model="valorEditorManual" aria-label="Institución" class="min-h-9 w-full rounded-lg border border-border bg-white px-3 text-sm">
                            <option value="">Selecciona una institución</option>
                            @foreach($institucionesCatalogo as $institucionOpcion)<option value="{{ $institucionOpcion }}">{{ $institucionOpcion }}</option>@endforeach
                        </select>
                    @elseif($campoEditorManual === 'Grupo Animal')
                        <select wire:model="valorEditorManual" aria-label="Grupo Animal" class="min-h-9 w-full rounded-lg border border-border bg-white px-3 text-sm">
                            <option value="">Selecciona un grupo</option>
                            @foreach($catalogoGrupos as $grupo)<option value="{{ $grupo['nombre'] }}">{{ $grupo['nombre'] }}</option>@endforeach
                        </select>
                    @elseif($campoEditorManual === 'Provincia')
                        <select wire:model="valorEditorManual" aria-label="Provincia" class="min-h-9 w-full rounded-lg border border-border bg-white px-3 text-sm">
                            <option value="">Selecciona una provincia</option>
                            @foreach($provinciasCatalogo as $provinciaOpcion)<option value="{{ $provinciaOpcion['nombre'] }}">{{ $provinciaOpcion['nombre'] }}</option>@endforeach
                        </select>
                    @elseif($campoEditorManual === 'Localidad')
                        <select wire:model.live="valorEditorManual" aria-label="Localidad" class="min-h-9 w-full rounded-lg border border-border bg-white px-3 text-sm">
                            <option value="">Selecciona una localidad de {{ $canton }}, {{ $provincia }}</option>
                            @foreach($localidadesCatalogo as $localidadOpcion)<option value="{{ $localidadOpcion }}">{{ $localidadOpcion }}</option>@endforeach
                            <option value="__OTRA__">Otra localidad de esta provincia…</option>
                        </select>
                        @if($valorEditorManual === '__OTRA__')
                            <div class="mt-2"><flux:input wire:model="localidadEspecifica" label="Nombre de la localidad" maxlength="140" placeholder="Área natural, parroquia o sitio de recolección" /><flux:error name="localidadEspecifica" /></div>
                        @endif
                    @else
                        <flux:input wire:model="valorEditorManual" :type="in_array($campoEditorManual, ['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes']) ? 'number' : 'text'" :min="in_array($campoEditorManual, ['N.º Individuos', 'N.º Lotes']) ? 1 : 0" :step="in_array($campoEditorManual, ['N.º Individuos', 'N.º Morfoespecies', 'N.º Lotes']) ? 1 : null" placeholder="Ingresa el dato" autofocus />
                    @endif
                    <flux:error name="valorEditorManual" />
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                    <flux:button variant="primary" wire:click="guardarEditorManual" wire:loading.attr="disabled" wire:target="guardarEditorManual">Guardar</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <div class="rounded-lg border border-bio-green/25 bg-bio-green/5 p-2">
        <div class="flex items-start gap-3">
            <flux:icon name="building-library" class="mt-0.5 size-5 shrink-0 text-bio-green" />
            <div>
                <p class="text-sm font-semibold text-text-primary">Datos que completa el museo</p>
                <p class="text-xs text-text-secondary">{{ \App\Support\WizardCopy::text('datos.uso_interno_explicacion') }}</p>
            </div>
        </div>
    </div>

</div>
