# Perfiles y recepción de depósitos

Los cinco perfiles asignables son Administrador (Curador), Técnico, Depositante, Pasante y Administrador-Creador. Técnico conserva el identificador AYUDANTE_CURADOR; Administrador-Creador conserva ADMIN. La migración reúne PRESTAMISTA en DEPOSITANTE y RECEPTOR en AYUDANTE_CURADOR sin eliminar cuentas ni expedientes. Depositante conserva las solicitudes de préstamo.

El Curador y el Creador pueden asignar Pasantes de confianza; el registro público no permite ese perfil. El Curador no puede crear, modificar ni eliminar cuentas Creador. El Pasante tiene una pantalla de captura con una lista blanca de campos Darwin Core, excluidos occurrenceID y los campos marcados de uso interno. El servidor genera los identificadores y registra los cambios. Las funciones de curaduría, importación, recepción y administración permanecen fuera de su acceso.

El público consulta la colección, descarga datos seleccionados y resultados de análisis y conserva la descarga individual de fotografías. No hay descarga masiva de fotografías. Las exportaciones completas y el formato IPT requieren un perfil del equipo autorizado.

## Flujo

1. Después de la firma, «Ver documento firmado» abre el PDF íntegro en el paso 1, a la izquierda del botón principal.
2. Al enviar la solicitud se notifica al solicitante, Curador, Técnico y Creador por correo, campana y push para dispositivos suscritos.
3. La aprobación documental asigna el QR automáticamente. El trabajo de correo prepara un único documento de ingreso privado, lo adjunta y conserva su SHA-256. La campana y el push abren una pantalla autenticada que inicia la descarga y conserva un botón de respaldo; el inicio de sesión conserva ese destino. No se reenvía correo por cada descarga.
4. El QR firmado abre el expediente para personal autorizado, sin recibir material por una consulta GET. Aprobar registra la recepción una sola vez y abre la etapa de contenido. Rechazar exige un motivo, conserva el historial y bloquea la recepción de esa solicitud.
5. El Técnico registra lotes o recipientes, cantidades, identificación, código de campo, preparación y diferencias. Para finalizar debe existir contenido tabulado y deben confirmarse el checklist y la revisión de todo el material. El expediente queda recibido y validado; se notifica al Curador y al Creador para continuar con el acta final. El contenido queda cerrado.

Las recepciones históricas ya iniciadas conservan su pantalla y el flujo de acta firmado. El ingreso a la colección sigue condicionado al acta final.

## Cobertura acotada

`PerfilesRecepcionDepositoTest.php` cubre permisos, manipulación de campos reservados, PDF único e íntegro, QR firmado, recepción idempotente, rechazo, tabulación obligatoria y destinatarios. Los contratos existentes de registro, usuarios, botones, canales y descargas se ajustan a las reglas solicitadas. Los cinco contratos nuevos de UI se ejecutan en localhost mediante MCP opera_neon desde `tests/Browser/contratos-ui-focales.mjs`; sus recibos son locales e independientes de los nueve recorridos históricos. La entrega real de correo y push a teléfonos requiere sus servicios y suscripciones operativas; las pruebas locales no acreditan una entrega remota.
