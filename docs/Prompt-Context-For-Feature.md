Actúa como un desarrollador Senior Fullstack experto en PHP, Laravel, Arquitectura Limpia, BDD (Behat) y Domain-Driven Design (DDD). Estás trabajando en el proyecto "Hub Digital".

Tu tarea es generar ÚNICAMENTE el archivo `Context` correspondiente para el archivo `.feature` que te proporcionaré al final.

Debes generar el código aplicando estrictamente las siguientes reglas, patrones y prohibiciones:

### 1. RESTRICCIONES DE ARQUITECTURA (LO QUE NO DEBES HACER)
* **PROHIBIDO Eloquent y Facades:** Nunca uses `Model::create()` ni `DB::table()`. Todo el acceso a datos debe hacerse inyectando las interfaces de los `Repositories` del dominio.
* **PROHIBIDO HTTP:** No uses clientes HTTP. Llama directamente a los Casos de Uso (Handlers) de la Capa de Aplicación.
* **PROHIBIDO Estado Estático:** Usa propiedades privadas de instancia (ej. `$this->ultimaRespuesta`, `$this->excepcionCapturada`).
* **PROHIBIDO Hooks de Behat para Sembrado:** NUNCA uses `@BeforeScenario` o similares para crear entidades base.
* **PROHIBIDO Implementar Lógica de Negocio:** No crees los Handlers, DTOs ni Entidades. Solo asume que existen e instáncialos en el Context.

### 2. PATRÓN DRY Y FACTORY METHODS (CÓMO SEMBRAR DATOS)
* Si notas que varios escenarios requieren la creación de la misma entidad base, crea un **método privado de ayuda (Helper/Factory Method)** (ej. `private function sembrar[Entidad]Base(): [Entidad]`).
* Este Helper debe generar el ID (`$repo->nextIdentity()`), instanciar la entidad con datos canónicos válidos, persistirla (`$repo->guardar()`), asignarla a una propiedad (ej. `$this->entidadExistente`) y retornarla.
* Llama a este Helper desde los pasos `@Given` pertinentes, mutando el estado específico solo después de su creación (y volviendo a guardar si hay mutación).

### 3. LÓGICA DE ASERCIONES PROFUNDAS (REGLA DE ORO)
Usa `PHPUnit\Framework\Assert` exhaustivamente, no solo para verificar resultados, sino para blindar la integridad del test:

* **En los pasos `@Given` (Precondiciones):** * Asertea que los datos sembrados cumplen con lo que dice el paso (ej. si dice "con información completa", asertea que los campos obligatorios no estén vacíos).
    * Si el paso implica pertenencia, asertea que el `actor_id` coincide con el dueño de la entidad.
    * Asertea que la entidad quedó persistida en el estado correcto antes de avanzar.
* **En los pasos `@When` (Acciones):**
    * ENVUELVE SIEMPRE la ejecución del Handler en un bloque `try/catch`. Guarda la respuesta en `$this->ultimaRespuesta` o el error en `$this->excepcionCapturada`. NO dejes que el error rompa el test.
    * *Para actualizaciones/ediciones:* Antes de ejecutar el Handler, asertea que la nueva información simulada sea **diferente** a la información ya persistida.
    * En caso de que el paso implique una acción que debería fallar por una regla de dominio, asertea que la información simulada es válida pero que el contexto (estado de la entidad, permisos del actor, etc.) es lo que causa el fallo.
* **En los pasos `@Then` (Validaciones):**
    * Asertea que `$this->excepcionCapturada` sea nulo (si es un caso de éxito) o no nulo (si es un caso de fallo/regla de dominio).
    *  Aqui siempre deben existir aserciones específicas
    * Para comparar estados, asume el uso de Value Objects o Enums (ej. `Assert::assertTrue($entidad->estado()->equals(Estado::Valor))`).
    * Si la base de datos debe cambiar, consulta el Repositorio nuevamente y asertea que los datos persistidos son correctos.

### 4. ESTRUCTURA Y LEGIBILIDAD
* La clase debe extender de `BaseContext`.
* Inyecta los Handlers necesarios en el constructor usando `$this->make(NombreHandler::class)`.
* **Agrupación visual:** Utiliza comentarios de bloque grandes para separar claramente a qué `Escenario` o `Esquema de Escenario` pertenecen los métodos (ej. `// ==========================================\n// ESCENARIO: Nombre del escenario\n// ==========================================`).
* Usa los nombres exactos de los campos de base de datos o dominio si te proveo contexto de entidades (ej. `institucion_adscripcion` en lugar de `institucion`).

### ARCHIVOS DE REFERENCIA:

**Archivo .feature a procesar:**
C:\Users\djimm\Herd\hubdigitalepn\Modules\GestionPrestamosRecepciones\tests\Behat\Contexts\TramitacionSolicitudesInvestigador\EnvioSolicitudPrestamoContext.php

**Contexto de Dominio/BD (Opcional pero recomendado):**
[PEGA AQUÍ UN RESUMEN DE TU ER O CAMPOS CLAVE SI LO TIENES, O BORRA ESTA LÍNEA]

Genera el código completo del Context ahora.
