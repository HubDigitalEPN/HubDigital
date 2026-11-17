<!doctype html>
<html lang="es"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#16324b;line-height:1.5}h1{font-size:22px}dt{font-weight:bold;margin-top:9px}dd{margin-left:0} .qr{text-align:center;margin-top:20px} .note{border-top:1px solid #ccc;padding-top:12px;margin-top:18px}
</style></head><body>
<p>Escuela Politécnica Nacional · Laboratorio de Invertebrados</p><h1>Documento de ingreso</h1>
<p>Solicitud {{ $solicitud->numero }} · {{ $solicitud->tipo_tramite }}</p>
<dl>
<dt>Titular / depositante</dt><dd>{{ $solicitud->solicitud_nombre_permiso ?: $solicitud->nombre_investigador_documento }}</dd>
<dt>Proyecto / institución</dt><dd>{{ $solicitud->solicitud_proyecto }} · {{ $solicitud->solicitud_institucion }}</dd>
<dt>Material declarado</dt><dd>{{ $solicitud->grupo_animal ?: $solicitud->solicitud_grupo }} · {{ $solicitud->nro_lotes ?? 'No declarado' }} lotes · {{ $solicitud->nro_individuos ?? 'No declarado' }} individuos</dd>
<dt>Localidad</dt><dd>{{ $solicitud->localidad ?: $solicitud->provincia_origen }}</dd>
<dt>Permisos de recolección / movilización</dt><dd>{{ $solicitud->nro_permiso_recoleccion ?: 'No declarado' }} / {{ $solicitud->nro_permiso_movilizacion ?: 'No declarado' }}</dd>
<dt>Aprobación documental</dt><dd>{{ \Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador::formatear($solicitud->aprobada_en) }} · Código {{ $solicitud->codigo_qr }}</dd>
</dl>
<div class="qr"><img width="180" height="180" alt="QR de validación del ingreso" src="data:image/svg+xml;base64,{{ $qr }}"><p>{{ $solicitud->codigo_qr }}</p></div>
<p class="note">Presenta este documento junto con el material físico. El Técnico escaneará el QR, comprobará el expediente y registrará la recepción. La aprobación documental no constituye recepción ni validación del contenido.</p>
<p>Documento único de la solicitud {{ $solicitud->id }}. Su autenticidad y estado vigente se consultan mediante el QR en HubDigital.</p>
</body></html>
