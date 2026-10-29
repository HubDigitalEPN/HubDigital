# Agente híbrido y presupuesto

El agente de HubDigital se ejecuta en Laravel para los chats del portal y de los depósitos. Admite preguntas generales, científicas y del sistema. La conversación abierta distribuye consultas mediante cinco grupos estables derivados de la pregunta: cuatro prefieren OpenAI (80 %) y uno Qwen (20 %). Es una proporción esperada entre consultas distintas; repetir una pregunta conserva el proveedor preferido. No significa mezclar porcentajes de palabras ni ejecutar ambos modelos en cada mensaje. Si el proveedor elegido falla se intenta el otro una sola vez; al agotarse el crédito OpenAI queda bloqueado y Qwen atiende el respaldo completo. Las clasificaciones de orientación conservan votos de peso 0,80 y 0,20.

Las respuestas publicadas, consultas reales del catálogo, permisos y validación de documentos siguen a cargo de HubDigital. El modelo no ejecuta SQL libre, no aprueba ni firma documentos y no inventa cantidades o estados privados. OpenAI solo puede inferir con `APP_ENV=production` y `APP_URL=https://labinvepn.org`; LOCAL y DEV quedan bloqueados aunque tengan una clave configurada. Qwen conserva su restricción de producción y un solo hilo de CPU.

## Corte automático

`PresupuestoIA` conserva un libro privado en microdólares, separado del caché y del código. Con USD 63 confirmados permite como máximo USD 59,85, reservando el 5 %. Antes de cada inferencia bloquea y reserva el coste máximo de entrada y salida; al recibir el consumo real liquida la diferencia. Dos workers comparten el mismo lock. No hay reintentos de inferencia. Los timeouts conservan su reserva porque el proveedor pudo procesarlos. Un consumo no verificable bloquea OpenAI.

Las comprobaciones ocurren en cada petición, sin cron ni intervención manual: saldo insuficiente, vencimiento, presupuesto ausente/corrupto/ocupado o error de crédito del proveedor devuelven inmediatamente el control a Qwen. Un aviso de crédito o límite agotado queda persistido, aunque se reinicie PHP, cambie la clave o se limpie el caché. No se compra saldo ni se agrega una tarjeta o recarga automática.

El registro inicial comprobado el 28 de octubre de 2026 corresponde a USD 63 recibidos ese día y vencimiento el 17 de noviembre de 2026. Como la interfaz solo publica la fecha, se corta conservadoramente al inicio del 17 de noviembre en UTC, sin inventar la hora exacta.

## Renovación y límite de la automatización

No se reinicia el dinero por cambiar de mes, desplegar o renovar una clave. La documentación pública consultada no ofrece un endpoint de asignaciones gratuitas con saldo, origen y vencimiento que se pueda autenticar con la clave de inferencia. Por ello no hay una reactivación mensual implementada ni se declara que una respuesta exitosa pruebe la existencia de un nuevo crédito gratuito. Al vencer la asignación, el sistema continúa con Qwen automáticamente y sin pedir revisiones manuales. Para reanudar OpenAI automáticamente hace falta una fuente autenticada de nuevas asignaciones; no se utilizan cookies del navegador, endpoints internos de facturación ni una sesión personal como servicio de producción.

El límite local cubre las llamadas que pasan por este cliente. Otros proyectos de la misma organización pueden consumir el saldo antes: en ese caso el error de OpenAI también provoca el corte. Mantener la cuenta sin plan pagado ni recarga evita un saldo de pago de respaldo; el límite local no controla cambios de facturación realizados fuera de HubDigital ni garantiza la factura de toda la organización. Los límites de gasto del proveedor, cuando estén disponibles, tienen propagación y no sustituyen la reserva local.

## Configuración y persistencia

Variables privadas: `IA_HIBRIDA_ENABLED`, `OPENAI_API_KEY`, `OPENAI_ORGANIZATION`, `OPENAI_PROJECT` y opcionalmente `IA_PRESUPUESTO_PATH`. Se documentan vacías en `.env.example`; nunca se publican claves. `config/ia.php` mantiene un único modelo con precio verificado, `gpt-6.1-terra`, con `reasoning.effort=low` y tarifa estándar USD 1/5 por millón de tokens de entrada/salida. Los tokens de razonamiento forman parte de `usage.output_tokens` y del máximo de salida, sin sumarlos otra vez. Se solicita `store=false`, sin herramientas, archivos, búsquedas ni niveles de servicio caros. Cambiar modelo o tarifas requiere actualizar este contrato y sus pruebas.

El presupuesto no se inicializa desde una petición HTTP. La instalación de la asignación inicial confirmada es una operación única de configuración. `PresupuestoIA::inicializar` rechaza sobrescribir un registro existente, incluso vencido o bloqueado. Nunca restaurar una copia antigua encima del saldo activo. En OCI la ruta predeterminada es `/var/lib/hubdigital/ia/presupuesto.json`, persistente entre releases y común a FPM/colas. Debe tener directorio 0700 y archivos 0600, accesibles por la cuenta de ejecución; la falta de esa instalación conserva Qwen. No compartir un presupuesto entre DEV y PROD ni habilitar dos contadores independientes para un mismo saldo gratuito.

`php artisan ia:presupuesto` muestra estado, saldo reservado y vencimiento sin contactar OpenAI. La implementación del presupuesto es código fuente versionado; el saldo real y las claves son estado privado, fuera de Git y de los paquetes OCI. Tampoco se exporta la base de evidencias de pruebas.

## Comprobación focalizada

`php vendor/bin/pest tests/Feature/AgenteHibridoPresupuestoTest.php Modules/CatalogoPublico/tests/Feature/ModeloLocalPequenoTest.php tests/Unit/AsistenteDocumentalDepositosTest.php --compact`

Se usan respuestas HTTP simuladas; no se consumen créditos ni se accede a una VM. No acredita el 30/70 completo ni una inferencia real.

### Configuración local comprobada el 29 de octubre de 2026

Se creó el proyecto dedicado «HubDigital - Asistente hibrido», limitado al modelo `gpt-5.6-terra`. Su clave tiene permisos restringidos a Responses y vencimiento el 17 de noviembre de 2026. Se guardó exclusivamente en el `.env` local, sin mostrarla en el chat ni incluirla en Git o en las salidas OCI. Se restringieron los permisos de Windows de ese archivo y del contador privado, conservando el propietario y los ejecutores locales.

Las 37 pruebas focalizadas previamente aprobadas se conservaron. Se ejecutaron únicamente los dos casos nuevos que habían quedado pendientes al pausar: comando de estado y orientación documental (2 aprobados, 6 aserciones). Una consulta mínima real desde localhost devolvió HTTP 200 y la opción aprobada `deposito`; su consumo registrado fue USD 0,000484. La conexión inicial bloqueada por la red del entorno restringido conserva una reserva de USD 0,042324, conforme al contrato de no liberar una llamada sin respuesta. El estado final del contador fue vigente, con límite USD 59,85 y saldo disponible USD 59,807192. No se reinició el contador ni se hicieron despliegues o activaciones en VM.

Esta configuración habilita la orientación híbrida de los asistentes del portal y de documentación de depósitos. La extracción adicional de campos desde PDF y la interpretación ampliada de filtros del catálogo no están implementadas en esta etapa.

Por solicitud posterior del usuario, el modelo activo se cambió a GPT-6.1 Terra con razonamiento bajo. Se conserva íntegro el contador anterior, incluidas sus reservas; no se modifica el saldo inicial ni el vencimiento. El proyecto permite únicamente `gpt-6.1-terra` y el cliente rechaza el modelo anterior. Los resultados de la primera comprobación se conservan como evidencia histórica de la configuración anterior.

El cambio de modelo se comprobó con cinco casos focalizados (23 aserciones, 4,49 segundos): configuración, liquidación, tokens de razonamiento, orientación documental y retorno a Qwen. La consulta real con `gpt-6.1-terra` y `reasoning.effort=low` devolvió HTTP 200, estado `completed` y la opción `deposito`, con 14 tokens de razonamiento incluidos en la salida. Se registraron USD 0,000307 adicionales; el gasto acumulado pasó de USD 0,000484 a USD 0,000791, conservando las reservas anteriores y el límite de USD 59,85. No se reejecutaron suites completas ni se accedió a las VM.

Referencias: [créditos de suscripción](https://developers.openai.com/api/docs/guides/chatgpt-api-credits), [límites de gasto](https://developers.openai.com/api/docs/guides/spend-limits), [modelo y precios](https://developers.openai.com/api/docs/models/gpt-6.1-terra), [salidas estructuradas](https://developers.openai.com/api/docs/guides/structured-outputs).


## Ampliación de conversación y entorno (29 de octubre)

La nueva conversación utiliza GPT-6.1 Terra con razonamiento bajo y hasta 1600 tokens de salida; la reserva previa ya incluye ese máximo. Se mantiene el mismo contador y vencimiento. Se comprobaron con HTTP simulado ambos chats, preguntas fuera del portal, el reparto 80/20 y el retorno a Qwen. Las pruebas locales no habilitan inferencias reales ni consumen el crédito de PROD.

La clave permanece únicamente en el `.env` local autorizado. El código de esta rama todavía requiere empaquetado y despliegue; para habilitar realmente OpenAI en PROD se debe autorizar la instalación privada de la clave y del contador persistente, conservando el gasto y las reservas anteriores. No incluir esos archivos en Git ni en OCI. No hay renovación automática de créditos sin una fuente autenticada que la confirme.
