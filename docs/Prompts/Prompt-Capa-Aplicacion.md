
# Prompt: Planificación de la Capa de Aplicación (Application Layer)

**Contexto del Proyecto:**
Continuamos con el desarrollo del sistema modular en PHP bajo Clean Architecture y DDD. La Capa de Dominio (Domain Layer) ya ha sido implementada, refinada y está cerrada. Ahora necesitamos planificar la Capa de Aplicación (Application Layer), encargada de orquestar los Casos de Uso (Use Cases) requeridos por el negocio.

### Insumos proporcionados:
1. **[Archivo .feature]:** (FUENTE DE VERDAD). Define los flujos exactos que el usuario necesita. Basarás la creación de los Casos de Uso en estos escenarios.
2. **[Carpeta Domain/]:** (REGLAS ESTRICTAS). Contiene el modelo rico que ya construimos. Debes revisar las Entidades, Value Objects, Excepciones e Interfaces de Repositorio para saber cómo interactuar con el dominio.
3. **[Archivo *Context.php]:** (SOLO REFERENCIA / BORRADOR). NO te dejes cegar por este archivo ni intentes diseñar la capa para que este código funcione tal cual. Úsalo ÚNICAMENTE para inspirarte en los nombres de los Casos de Uso, pero la lógica y el flujo deben basarse 100% en el `.feature` y en el `Domain/`.

---

### Tu Tarea:
Actúa como un **Arquitecto de Software**. Debes generar un Plan de Implementación Detallado ÚNICAMENTE para la Capa de Aplicación. **No escribas código final**, preséntame el plan (seudocódigo). NO modifiques la Capa de Dominio bajo ninguna circunstancia.

---

### Directrices Estrictas de la Capa de Aplicación:

#### 1. Alineación con Guidelines y Skills:
Tus Casos de Uso deben seguir el patrón de diseño estandarizado del proyecto (**Handlers, Inputs/DTOs, Outputs**). El Input debe ser una `readonly class` con tipos primitivos.

#### 2. Responsabilidad de Orquestación (Cero Lógica de Negocio):
Los Handlers en esta capa son "tontos" respecto al negocio. Su flujo estricto debe ser:
* **Recibir Input:** DTO con tipos primitivos (`string`, `int`, `array`).
* **Transformación (Mapeo a VOs):** Instanciar los Value Objects requeridos por el Dominio.
* **Recuperación:** Usar las Interfaces de los Repositorios para obtener la Entidad (o instanciar una nueva con el factory del dominio).
* **Ejecución:** Llamar al método de comportamiento en la Entidad (ej. `$entidad->enviar()`).
* **Persistencia Atómica y Eventos:** (Ver punto 3).
* **Retorno:** Devolver un Output/DTO usando un método `fromPrimitives()`. NUNCA devuelvas Entidades de Dominio directamente hacia el exterior.

#### 3. Desacoplamiento de Base de Datos (Transacciones):
Está **PROHIBIDO** usar abstracciones de frameworks (como `DB::transaction()` de Laravel) en esta capa. Para garantizar la atomicidad, debes inyectar un puerto `TransactionManagerPort`. El guardado (`$repo->guardar()`) y la publicación de eventos (`$entidad->pullEvents()`) deben estar orquestados dentro de:
`$this->transactionManager->executeTransactional(fn() => ... )`.

#### 4. Trazabilidad de IDs en Colecciones (Actualizaciones):
Si el Caso de Uso implica actualizar o enviar colecciones de ítems en arreglos:
* Nombra a la variable de iteración como `$datosItem` o un nombre acorde al contexto de la feature (evita `$raw` o nombres genéricos).
* Es obligatorio que en el seudocódigo valides la existencia del ID para no perder la trazabilidad.
* **Ejemplo esperado:** `id: isset($datosItem['id']) ? ItemId::fromString($datosItem['id']) : ItemId::generate()`

#### 5. Respetar la Arquitectura de Identificadores:
Separa los **System IDs** (UUIDs) de los **Business IDs** (ej. NumeroSolicitud). Asegúrate de generar y mapear ambos correctamente cuando se creen nuevas entidades.

---

### Entregable esperado:
Presenta un **Plan de Diseño Estructurado** que incluya:
1.  Un listado de los **Use Cases** a implementar, derivados directamente de los escenarios del `.feature`.
2.  Un **directorio de destino** propuesto siguiendo la estructura modular.
3.  Un **seudocódigo** o explicación paso a paso de la orquestación dentro de los Handlers principales, mostrando explícitamente:
    * Mapeo de primitivos a Value Objects.
    * Uso del `TransactionManagerPort`.
    * Lógica de `$datosItem` con el manejo de IDs (isset/generate).
    * Retorno del Output mapeado a primitivos.

**¿Entendido? Analiza los insumos y genera el plan.**

***
