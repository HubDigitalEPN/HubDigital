@if(!empty($firmasElectronicas))
    @php
        $sinValidar = array_filter($firmasElectronicas, fn ($estado) => !in_array($estado, ['firmado', 'firmado_sin_revocacion'], true));
        $soloFallaRevocacion = $sinValidar !== [] && count(array_filter($sinValidar, fn ($estado) => $estado === 'revocacion_no_comprobable')) === count($sinValidar);
        $puedeReintentar = array_intersect($firmasElectronicas, ['validando', 'verificacion_no_disponible', 'certificado_no_confiable', 'almacen_incompleto', 'revocacion_no_comprobable', 'firmado_sin_revocacion', 'no_verificado', 'firma_invalida', 'certificado_caducado']) !== [];
        $motivosFirma = [
            'firmado' => 'Firma criptográfica válida y certificado de confianza.',
            'validando' => 'Se está comprobando la firma electrónica.',
            'sin_firma' => 'El PDF no contiene una firma electrónica utilizable.',
            'firma_invalida' => 'La firma no supera la comprobación criptográfica de integridad o cobertura.',
            'documento_modificado' => 'La firma es válida, pero el PDF tiene cambios posteriores que no están firmados.',
            'certificado_caducado' => 'El certificado estaba caducado en la fecha de firma comprobada.',
            'certificado_aun_no_vigente' => 'El certificado aún no era válido en la fecha comprobada.',
            'certificado_revocado' => 'El certificado fue revocado por su emisor.',
            'certificado_no_confiable' => 'No se pudo establecer una cadena de confianza aceptada.',
            'almacen_incompleto' => 'La firma es íntegra; falta el certificado emisor o raíz en el almacén. Se revisará antes de aprobar.',
            'verificacion_no_disponible' => 'La verificación criptográfica no está disponible en este servidor.',
            'no_verificado' => 'No se obtuvo un resultado concluyente de la firma.',
            'firmado_sin_revocacion' => 'Firma, integridad, vigencia y cadena validadas. Estado de revocación no comprobado.',
            'revocacion_no_comprobable' => 'La firma es íntegra, pero no respondió una fuente vigente de revocación del emisor. Vuelve a intentar la consulta.',
        ];
    @endphp
    <section class="space-y-2 rounded-lg border {{ $sinValidar ? 'border-error/35 bg-error/5' : 'border-science-blue/30 bg-science-blue/5' }} p-3" aria-label="Resultado de firmas electrónicas">
        <div class="flex items-start gap-2">
            <flux:icon :name="$sinValidar ? 'shield-exclamation' : 'shield-check'" class="mt-0.5 size-5 shrink-0 {{ $sinValidar ? 'text-error' : 'text-science-blue' }}" />
            <div>
                <p class="text-sm font-semibold text-text-primary">{{ $soloFallaRevocacion ? 'No se pudo comprobar la revocación' : ($sinValidar ? 'Corrige las firmas para continuar' : 'Firmas electrónicas verificadas') }}</p>
                @if($sinValidar)<p class="text-xs text-text-secondary">Revisa el resultado de cada PDF. Puedes volver a comprobar las firmas cuando falte una respuesta del servicio o un certificado emisor.</p>@endif
            </div>
        </div>
        <div class="grid gap-1 sm:grid-cols-2">
            @foreach($firmasElectronicas as $nombre => $estado)
                <div class="rounded-md border border-border bg-white px-2.5 py-2 text-xs">
                    <p class="font-semibold text-text-primary">{{ $nombre }}</p>
                    <p class="mt-0.5 {{ in_array($estado, ['firmado', 'firmado_sin_revocacion'], true) ? 'text-success' : 'text-error' }}">{{ $motivosFirma[$estado] ?? $motivosFirma['no_verificado'] }}</p>
                </div>
            @endforeach
        </div>
        @if($puedeReintentar)
            <flux:button variant="outline" size="sm" icon="arrow-path" wire:click="repetirVerificacionFirmas" wire:loading.attr="disabled" wire:target="repetirVerificacionFirmas">Comprobar firmas de nuevo</flux:button>
        @endif
    </section>
@endif
