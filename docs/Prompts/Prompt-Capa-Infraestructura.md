# Prompt: Planificación de la Capa de Infraestructura (Infrastructure Layer)

**Contexto del Proyecto:**
Continuamos con el desarrollo del sistema modular en PHP bajo Clean Architecture y DDD. Las capas de Dominio (Domain) y Aplicación (Application) ya están implementadas, probadas y cerradas. Ahora necesitamos planificar la Capa de Infraestructura (Infrastructure Layer). Recuerda que este diseño contempla una capa adicional posterior llamada "Presentación", por lo que la Infraestructura **NO debe tocar nada relacionado con HTTP o la API**.

### Insumos proporcionados:
1.  **[Archivos de Dominio]: (ESTRICTOS)** Especialmente las interfaces de los Repositorios y la estructura de las Entidades y Value Objects. Debes basar el mapeo de base de datos en estos.
2.  **[Archivos de Aplicación]: (ESTRICTOS)** Especialmente los Puertos (ej. `TransactionManagerPort`, `EventPublisherPort`) que necesitan ser implementados.
3.  **[Archivo .feature]: (CONTEXTO)** Para entender la magnitud de los datos que finalmente deben persistirse y las relaciones del negocio.

---

### Tu Tarea:
Actúa como un **Arquitecto de Software y Experto en Laravel/Postgres**. Debes generar un Plan de Implementación Detallado ÚNICAMENTE para la Capa de Infraestructura del módulo correspondiente. **No escribas código final todavía**, preséntame el plan (seudocódigo estructurado). NO modifiques las capas de Dominio ni Aplicación bajo ninguna circunstancia.

---

### Directrices Estrictas de la Capa de Infraestructura:

#### 1. Responsabilidad de la Capa (Adaptadores y Persistencia):
Esta capa es el "detalle técnico". Aquí **SÍ** está permitido usar herramientas de frameworks (ej. Eloquent, Facades de Laravel, DB). Su único objetivo es proveer las implementaciones concretas que el Dominio y la Aplicación solicitaron mediante interfaces.

#### 2. Modelos de Base de Datos (Anémicos) vs. Entidades de Dominio:
* Los modelos de Eloquent (`extends Model`) pertenecen a esta capa y **deben ser estrictamente anémicos**.
* Solo deben contener `$table`, `$primaryKey`, `$fillable` (mapeo de columnas) y las relaciones nativas de Eloquent para eager loading.
* **CERO lógica de negocio**, CERO mutators o accessors complejos en los modelos. No son Entidades de Dominio.

#### 3. Estrategia de Data Mapper "Inline" (Repositorios):
No crees clases de abstracción "Mapper" separadas; la lógica va dentro de los Repositorios concretos.
* **Al Guardar (toEloquent):** Extrae los valores escalares de los Value Objects (ej. `$entity->id()->value()`). Usa métodos como `updateOrCreate` para garantizar el estado del Agregado. Si hay colecciones (ítems), debes sincronizarlas (eliminar las que ya no están y actualizar/crear las vigentes).
* **Al Consultar (toDomain):** Reconstituye la Entidad de Dominio. Usa métodos estáticos en el Dominio (ej. `Entidad::reconstituir(...)`) para bypasear las validaciones del constructor estándar cuando la data provenga de la base de datos.

#### 4. Implementación de Puertos de Aplicación:
Planifica las clases concretas (Adaptadores) que implementarán los puertos:
* **TransactionManagerPort:** Debe envolver la transacción nativa (ej. `DB::transaction()`).
* **EventPublisherPort:** Debe despachar los eventos de dominio como POPOs (Plain Old PHP Objects) a través del bus del framework (ej. `event($event)` en Laravel), sin obligar al Dominio a extender clases externas.

#### 5. Diseño de Base de Datos (Migraciones) e Identificadores:
* Respeta la arquitectura de identificadores: El **System ID** (UUID) debe ser la Primary Key (`id` tipo uuid). Si el agregado tiene un **Business ID** (ej. NumeroSolicitud), este debe ser una columna string con restricción `UNIQUE`.
* Configura correctamente las Foreign Keys (con `onDelete('cascade')` donde aplique) para mantener la integridad del Agregado en base de datos.

#### 6. Frontera Estricta con Presentación (Prohibiciones):
* **CERO** Controladores (Controllers).
* **CERO** Form Requests, validaciones HTTP o middlewares.
* **CERO** Rutas (Routes/API).
* **CERO** Vistas o respuestas JSON.
  *(Todo esto pertenecerá a la capa de Presentación en la fase final).*

#### 7. Inyección de Dependencias:
Planifica un `ServiceProvider` del módulo donde se registrarán los bindings entre las Interfaces (Dominio/App) y sus Implementaciones concretas, además de cargar las migraciones del módulo.

---

### Entregable esperado:
Presenta un **Plan de Diseño Estructurado** que incluya:
1.  **Estructura de directorios** a crear (Persistence/Eloquent, Adapters, Providers, Migrations).
2.  **Diseño de las Migraciones:** Tablas, tipos de datos, PKs UUID, Unique constraints para Business IDs y FKs.
3.  **Modelos Eloquent:** Listado de los modelos anémicos y sus relaciones (`hasMany`, `belongsTo`).
4.  **Estrategia del Data Mapper (Seudocódigo):** Un ejemplo breve de cómo un Repositorio guardará y reconstituirá el Aggregate Root (incluyendo extracción de VOs y manejo de ítems hijos).
5.  **Adaptadores:** Listado de las clases que implementarán los puertos de Application.
6.  **ServiceProvider:** Los bindings planificados (ej. `$this->app->bind(...)`).

**¿Entendido? Analiza los insumos adjuntos y genera el plan de Infraestructura.**
