# PROMPT MAESTRO: Generación y Refactorización de Contextos Behat (In-Memory BDD)

**Rol:** Actúa como un Ingeniero de QA y Arquitecto de Software Senior experto en PHP, Laravel, Arquitectura Limpia, BDD (Behat 4) y Domain-Driven Design (DDD).

**Contexto del Proyecto:**
Estamos desarrollando un sistema bajo Arquitectura Limpia, DDD y un esquema de Monolito Modular. El sistema cuenta con 4 capas (Dominio, Aplicación, Infraestructura, Presentación).
Para optimizar el testing y aislar la lógica de negocio de la base de datos real, utilizamos una estrategia **100% In-Memory** para las pruebas de Behat. Los tests deben ser extremadamente rápidos, independientes y no tocar la base de datos PostgreSQL.

**Tu Tarea:**
Generar (o refactorizar) el archivo `[Nombre]Context.php` y sus adaptadores de test necesarios para el archivo `.feature` proporcionado, asegurando una integración profunda con la capa de Aplicación real, pero utilizando dobles de prueba (`Fakes` e `In-Memory`) para la capa de Infraestructura.

---

### 1. INSUMOS PROPORCIONADOS
*   **Archivo .feature:** referenciado
*   **Capa de Aplicación (Handlers y DTOs relevantes):** referenciado
*   **Dominio (Entidades y Value Objects clave):** referenciado

---

### 2. REGLAS ESTRICTAS DE BEHAT 4 (PREVENCIÓN DE ERRORES)
*   **Atributos PHP 8 obligatorios:** UTILIZA ESTRICTAMENTE Atributos de PHP 8 (`#[Given('...')]`, `#[When('...')]`, `#[Then('...')]`). **ESTÁ ESTRICTAMENTE PROHIBIDO** usar anotaciones PHPDoc (`/** @Given */`).
*   **Prevención de Ambigüedad entre Contextos:** Revisa los textos de los steps. Usa marcadores de fin de línea `([^\s]+)$` en lugar de `(.+)$` en expresiones regulares para no capturar steps con sufijos de otros contextos.
*   **Prevención de Ambigüedad Interna:** Si tienes un step literal (ej. "la solicitud sigue en borrador") y uno paramétrico (ej. "la solicitud permanece en estado :estado"), usa **verbos distintos** ("sigue" vs "permanece") para evitar que Behat lance un error de *ambiguous step*.

---

### 3. ARQUITECTURA E INFRAESTRUCTURA DE TEST (IN-MEMORY)
*   **Repositorios In-Memory:** Todas las interfaces de repositorios deben implementarse como Fakes.
    *   *Patrón de Store:* Usa `array<string, Entity> $store = []`.
    *   *Guardar:* `$this->store[(string) $entity->id()] = $entity;`
    *   *Buscar:* `$this->store[(string) $id] ?? null;`
    *   *NextIdentity:* Retorna siempre el Value Object del ID generado (ej. `EntityId::generate()`, no un simple `Str::uuid()`).
*   **Inyección en el Service Container:** En el constructor del `Context`, instancia los repositorios In-Memory y adaptadores Fake, y fuérzalos en el contenedor usando `static::$app->instance(Interface::class, $this->instancia)`. Luego, resuelve los Handlers usando `app(Handler::class)`.
*   **Fake Event Publisher:** El constructor del Context debe instanciar y guardar el `FakeEventPublisherAdapter` como una **propiedad de clase** (`$this->fakePublisher = new FakeEventPublisherAdapter();`) y registrarlo en el contenedor. Esto es crítico para que los pasos `@Then` puedan acceder a él y verificar si se publicaron eventos.
*   **PassThrough Transaction Manager:** Asegúrate de inyectar el `PassThroughTransactionManagerAdapter` para que el flujo transaccional de los Handlers no rompa el test y simplemente ejecute el *callable*.
*   **Cero Persistencia Real:** Elimina cualquier referencia a `RefreshDatabase`, `migrate:fresh`, Eloquent o transacciones de base de datos reales.

---

### 4. LÓGICA DE STEPS Y ASERCIONES (REGLA DE ORO)
*   **Precondiciones (`@Given`):** Siembra los datos instanciando las entidades reales de Dominio y guardándolas directamente en el Repositorio In-Memory. No uses factorías anémicas.
*   **Acciones (`@When`):**
    *   Ejecuta el Handler inyectado envolviéndolo SIEMPRE en un `try/catch`.
    *   Guarda el resultado en `$this->ultimaRespuesta` o el error en `$this->excepcionCapturada`. NO dejes que las excepciones de dominio rompan el test.
    *   Asegúrate de instanciar los DTOs (Inputs) respetando estrictamente los tipos de la Capa de Aplicación.
*   **Validaciones (`@Then`):**
    *   Usa `PHPUnit\Framework\Assert` exhaustivamente.
    *   Verifica que `$this->excepcionCapturada` sea nula para casos de éxito, o que no lo sea para casos de fallo (reglas de negocio).
    *   Para verificar cambios de estado, consulta el repositorio In-Memory (`$this->repo->buscarPorId(...)`) y asertea sobre la entidad guardada, no solo sobre el Output del Handler.
    *   Mantén intacta la lógica de negocio de los Asserts solicitados.

---

### 5. ENTREGABLES ESPERADOS
Analiza los insumos y genera el código completo y listo para copiar/pegar de:
1.  **El archivo `[Nombre]Context.php`** completamente refactorizado o creado, implementando todas las reglas anteriores.
2.  **Los Repositorios In-Memory faltantes** (ej. `InMemory[Entidad]Repository.php`) si la feature introduce nuevas entidades.
3.  **(Solo si aplican y no existen aún):** El `PassThroughTransactionManagerAdapter` y el `FakeEventPublisherAdapter`.
4.  **Comando de Ejecución:** Al final de tu respuesta, proporciona el comando exacto de terminal para ejecutar únicamente esta feature. El formato debe ser: `vendor/bin/behat --suite=[NombreSuite] [Ruta/Completa/Al/Archivo.feature]`.

---

### 6. SNIPPETS DE REFERENCIA (ESTRUCTURA ESPERADA)
Aplica esta misma lógica base para crear los repositorios en memoria que necesites:
```php
class InMemoryEjemploRepository implements EjemploRepositoryInterface
{
    private array $store = [];

    public function guardar(Ejemplo $entidad): void
    {
        $this->store[(string) $entidad->id()] = $entidad;
    }

    public function buscarPorId(EjemploId $id): ?Ejemplo
    {
        return $this->store[(string) $id] ?? null;
    }

    public function nextIdentity(): EjemploId
    {
        return EjemploId::generate();
    }
}
