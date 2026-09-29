# Distribución de las pruebas

Pest conserva las comprobaciones de componentes, las pruebas de persistencia y los recorridos completos que ya tiene implementados. Gherkin, ejecutado por Behat, añade contratos de negocio y recorridos entre componentes con resultados que Pest todavía no comprueba.

Un duplicado tiene las mismas entradas, el mismo recorrido y el mismo resultado comprobado. Usar un mismo componente para preparar datos no convierte dos recorridos distintos en un duplicado. Antes de añadir un escenario, revisar las pruebas activas de `phpunit.xml` y los escenarios `@listo` de Behat.

## Casos que se conservan en Pest

Se retiraron los escenarios Gherkin equivalentes y sus pasos exclusivos. Los repositorios en memoria compartidos con Pest se conservan.

| Caso | Pruebas que lo conservan |
| --- | --- |
| Actualizar datos de un espécimen y rechazar un identificador inexistente | `Modules/InventarioGestionColeccion/tests/Unit/ActualizarEspecimenHandlerTest.php` |
| Desactivar un taxón y rechazar un identificador inexistente | `Modules/InventarioGestionColeccion/tests/Unit/DesactivarTaxonHandlerTest.php` |
| Registrar localidades, validar duplicados y jerarquía, y listar con paginación | `Modules/InventarioGestionColeccion/tests/Unit/RegistrarLocalidadHandlerTest.php` y `ListarLocalidadesHandlerTest.php` |
| Registrar, confirmar y marcar para revisión una muestra de colecta | `Modules/InventarioGestionColeccion/tests/Unit/RegistrarMuestraColectaHandlerTest.php`, `ConfirmarMuestraHandlerTest.php` y `MarcarMuestraParaRevisionHandlerTest.php` |
| Rechazar una consulta fuera del dominio sin invocar al generador de respuestas | `Modules/CatalogoPublico/tests/Unit/ConsultarChatBotHandlerTest.php` |
| Enviar la solicitud firmada, pasarla a revisión y notificar a curaduría | `tests/Unit/EnviarSolicitudDepositoInvariantesTest.php` y `tests/Feature/FlujoDepositoPersistenciaE2ETest.php` |
| Recepción conforme de un depósito hasta su ingreso científico | `tests/Feature/FlujoDepositoPersistenciaE2ETest.php` |

La comprobación del evento `SolicitudDepositoPendienteDeRevision`, antes exclusiva del escenario de envío, se trasladó al caso Pest de envío firmado. Ese caso también comprueba que repetir el envío no vuelva a publicar el evento. Así se conserva la comprobación al retirar el escenario duplicado.

En la aprobación documental sin alertas se retiraron las comprobaciones repetidas de estado, QR y notificaciones. Gherkin conserva la auditoría del curador responsable, su fecha y el evento de aprobación. También conserva los recorridos de justificaciones, rechazo, donación y prioridad que tienen condiciones propias.

## Cuatro recorridos adicionales del QR

Están en [lectura_qr_movil.feature](../Modules/InventarioGestionColeccion/tests/Behat/Features/IdentificacionFisicaEspecimenes/lectura_qr_movil.feature), marcados como `@listo` por la característica.

| Recorrido Gherkin | Comprobación adicional |
| --- | --- |
| Generar la etiqueta, corregir localidad y colector y resolver el QR original | La ficha muestra los datos corregidos y conserva el taxón, el espécimen, el código de catálogo y la etiqueta. |
| Generar la etiqueta, cambiar la determinación científica y resolver el QR original | La ficha muestra el taxón actual sin cambiar la etiqueta ni su vínculo con el espécimen. |
| Etiquetar dos especímenes, corregir el primero y resolver ambas etiquetas | Cada ficha conserva su identidad y sus propios datos; la corrección no se propaga al segundo espécimen. |
| Etiquetar un espécimen indeterminado, consultar su ficha, identificarlo y volver a consultar | La misma etiqueta pasa de mostrar una ficha sin taxón a mostrar la primera determinación científica. |

Pest conserva las pruebas individuales de actualización e identificación. Estos escenarios comprueban su efecto posterior en la ficha resuelta desde una etiqueta existente. Ejecutan los componentes reales con repositorios en memoria; no son pruebas del navegador ni sustituyen la persistencia PostgreSQL de Pest.

Gherkin también conserva la auditoría de aprobación, la recepción permanente de donaciones y la transferencia de campos científicos al contexto del chatbot. La recepción normal del depósito se prueba en Pest; Gherkin mantiene los recorridos de corrección y cuarentena por observaciones.

## Validación y publicación

El usuario ejecuta `crear-paquete-oci`. El comando exige Behat, ejecuta `--profile=default --no-interaction --tags=@listo --strict` y detiene la publicación y el empaquetado ante un código de salida distinto de cero. La etiqueta y el perfil coinciden con los escenarios activos de GitHub. Los escenarios heredados aún sin implementar permanecen fuera de esa selección; no se deben quitar etiquetas para ocultar fallos de escenarios activos.

La validación Gherkin ocurre antes del commit, de la publicación en `main` y de la creación del archivo OCI. Las otras comprobaciones obligatorias del paquete también deben terminar correctamente. Estas pruebas se conservan en Git y sus archivos y dependencias de desarrollo se excluyen del paquete de producción.

Los cambios se revisan de forma estática durante el desarrollo. La ejecución automatizada queda a cargo del comando del usuario; añadir un escenario no significa que ya haya pasado.
