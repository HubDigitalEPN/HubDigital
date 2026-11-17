# Perfiles y recepción de depósitos

Los cinco perfiles asignables son Administrador (Curador), Técnico, Depositante, Pasante y Administrador-Creador. Técnico conserva el identificador AYUDANTE_CURADOR; Administrador-Creador conserva ADMIN. La migración reúne PRESTAMISTA en DEPOSITANTE y RECEPTOR en AYUDANTE_CURADOR sin eliminar cuentas ni expedientes. Depositante conserva las solicitudes de préstamo.

El Curador y el Creador pueden asignar Pasantes de confianza; el registro público no permite ese perfil. El Curador no puede crear, modificar ni eliminar cuentas Creador. El Pasante tiene una pantalla de captura con una lista blanca de campos Darwin Core, excluidos occurrenceID y los campos marcados de uso interno. El servidor genera los identificadores y registra los cambios. Las funciones de curaduría, importación, recepción y administración permanecen fuera de su acceso.

El público consulta la colección, descarga datos seleccionados y resultados de análisis y conserva la descarga individual de fotografías. No hay descarga masiva de fotografías. Las exportaciones completas y el formato IPT requieren un perfil del equipo autorizado.

## Flujo

1. Después de la firma, «Ver documento firmado» abre el PDF íntegro en el paso 1, a la izquierda del botón principal.
2. Al enviar la solicitud se notifica al solicitante, Curador, Técnico y Creador por correo, campana y push para dispositivos suscritos.
3. La aprobación documental asigna el QR y registra un aviso pendiente en la misma transacción. El trabajador prepara el documento de ingreso privado desde la copia de los datos aprobados, verifica su almacenamiento y SHA-256 y lo adjunta al correo. La campana y el push abren una pantalla autenticada que inicia la descarga y conserva un botón de respaldo; el inicio de sesión conserva ese destino. No se reenvía correo por cada descarga.
4. El QR firmado abre el expediente para personal autorizado, sin recibir material por una consulta GET. «Recibir material» registra la recepción una sola vez y abre la etapa de contenido. «No recibir entrega» exige un motivo y conserva la aprobación documental. Bloquea otro intento hasta que un Curador o Creador lo autorice con motivo; el Técnico no tiene esa capacidad. El historial conserva autor, fecha y motivo de cada decisión. El rechazo documental definitivo sigue siendo una decisión distinta.
5. El Técnico registra lotes o recipientes, cantidades, identificación, código de campo, preparación y diferencias. Para finalizar debe existir contenido tabulado y deben confirmarse el checklist y la revisión de todo el material. El expediente queda recibido y validado; se notifica al Curador y al Creador para continuar con el acta final. El contenido queda cerrado.

Las recepciones históricas ya iniciadas conservan su pantalla y el flujo de acta firmado. El ingreso a la colección sigue condicionado al acta final.

## Arquitectura del ingreso

Se conserva el módulo Laravel/Livewire actual y PostgreSQL como registro del expediente. Las reglas de datos declarados y la validación del contenido pertenecen al dominio; los casos de uso las aplican también al invocarse directamente. `FlujoIngresoDeposito` deriva las capacidades visibles a partir de la aprobación, la entrega, la validación y el acta. Un expediente finalizado conserva documentos e historial y deja de ofrecer acciones de ingreso.

| Documento | Momento | Responsable |
| --- | --- | --- |
| Oficio de solicitud | Preparación de la solicitud | Firma del depositante |
| Documento de ingreso con QR | Aprobación documental | Generación automática del sistema |
| Acta final de recepción | Después de validar el contenido físico | Flujo de revisión y firma de Curaduría |

`recepciones.ingreso_pendientes` guarda el aviso durable y una copia de los datos aprobados. El renderizado PDF, R2 y el transporte de correo se ejecutan fuera del bloqueo de la solicitud. Una descarga concurrente conserva el documento que se publicó primero y verifica sus bytes. Los PDF históricos ya persistidos no se regeneran.

El planificador ejecuta `php artisan depositos:ingresos-pendientes` cada minuto y despacha como máximo 50 pendientes por ejecución al trabajador existente. Una reserva de seis minutos permite recuperar trabajos perdidos. Cada pendiente admite tres intentos automáticos en total; después requiere intervención explícita. Las fechas del outbox llevan zona UTC explícita para no depender de la zona de la conexión PostgreSQL. No hay sondeos ni reintentos ilimitados.

La referencia del aviso significa **programado**. Solo una respuesta efectiva del transporte marca el correo como aceptado, lo cual no acredita que llegó a la bandeja del destinatario. Si el proceso se interrumpe durante el envío o un canal no confirma su ejecución, Curaduría debe revisar el resultado y autorizar un posible reenvío. El modo de validación sigue bloqueando notificaciones externas y no se presenta como un envío real.

La nueva finalidad «Investigación / Consultoría» es independiente de «Depósito / Donación». La consultoría nacional requiere permisos de recolección y movilización; la investigación conserva la vía de justificación revisada por Curaduría. El paso de origen exige una localidad activa del catálogo INEC de la provincia seleccionada; los lugares faltantes deben darse de alta por administración. Los expedientes históricos no reciben una finalidad inventada mediante la migración.

Los totales encontrados en permisos se conservan como resultados de extracción, sin convertirse en cantidades del depósito. El depositante declara individuos, morfoespecies y lotes. Repetir la extracción conserva esas cantidades declaradas. Los casos de uso del flujo nuevo impiden finalizar la recepción por la ruta histórica sin registrar y validar antes el contenido.

## Cobertura acotada

`PerfilesRecepcionDepositoTest.php` cubre permisos, manipulación de campos reservados, PDF único e íntegro, QR firmado, recepción idempotente, rechazo, tabulación obligatoria y destinatarios. Los contratos existentes de registro, usuarios, botones, canales y descargas se ajustan a las reglas solicitadas. Los contratos de UI se ejecutan en localhost mediante MCP opera_neon desde `tests/Browser/contratos-ui-focales.mjs`; sus recibos son locales e independientes de los nueve recorridos históricos. La entrega real de correo y push a teléfonos requiere sus servicios y suscripciones operativas; las pruebas locales no acreditan una entrega remota.

`DepositosArquitecturaTest.php` añade atomicidad de aprobación y pendiente, recuperación, snapshot, reintentos acotados, resultado incierto del correo, transporte local con adjunto, finalidad, INEC, cierre histórico y bloqueo de atajos hacia el acta. Los contratos UI `depositos_origen`, `depositos_rechazo`, `depositos_reintento` y `depositos_cerrado` complementan los recorridos existentes de ingreso, contenido y descarga. Sus recibos comprueban las huellas de fuentes y build antes y después, exclusivamente mediante Opera Neon QA.
