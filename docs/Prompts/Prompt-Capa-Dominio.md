
# Prompt: Planificación de la Capa de Dominio (Domain Layer)

**Contexto del Proyecto:**
Estoy trabajando en un sistema modular en PHP siguiendo estrictamente Clean Architecture y Domain-Driven Design (DDD). Utilizamos Behat para las pruebas de aceptación (BDD).

### Insumos proporcionados:
1.  **[Archivo .feature]: (OBLIGATORIO)** Es la única fuente de verdad. Define el comportamiento esperado del negocio.
2.  **[Archivo *Context.php]: (REFERENCIA TÉCNICA)** Tómalo SOLO como una guía preliminar. **ADVERTENCIA CRÍTICA:** No debes seguir este archivo al pie de la letra. No contamines el Dominio creando métodos artificiales o constructores basura (ej. `crearIncompleta()`) solo para que los steps actuales del contexto no fallen. El Dominio dicta las reglas de negocio; los tests se adaptarán al Dominio más adelante.
3.  **[Diagrama ER u otros]: (OPCIONAL)** Úsalo para entender las relaciones y atributos de persistencia, pero recuerda que el diseño del dominio debe nacer del comportamiento, no de las tablas.

---

### Tu Tarea:
Actúa como un **Arquitecto de Software experto en DDD**. Debes generar un Plan de Implementación Detallado ÚNICAMENTE para la Capa de Dominio (Domain Layer) del módulo correspondiente al `.feature`. NO implementes Application, Infrastructure ni Presentation. **No escribas código final todavía**, preséntame el plan.

---

### Directrices Estrictas de la Capa de Dominio:

#### 1. Alineación con el Proyecto:
Antes de proponer el plan, analiza proactivamente los archivos de Guidelines (estándares de codificación) y Skills (patrones de diseño) que ya existen en el contexto del proyecto. Tu plan debe ser 100% consistente con la arquitectura actual.

#### 2. Pureza de Infraestructura (Cero Ruido de Persistencia):
* El código de dominio debe ser PHP puro. Prohibido usar clases de frameworks externos (ej. `Illuminate`).
* **NO** incluyas atributos genéricos de auditoría de bases de datos como `creadaEn` o `actualizadaEn` en las entidades. La persistencia técnica es problema de Infraestructura.
* **SÍ** debes incluir fechas semánticas que representen hitos reales del negocio (ej. `enviadaEn`, `aprobadaEn`) si el `.feature` lo requiere.

#### 3. Diseño de Entidades vs. Value Objects:
* Define claramente el **Aggregate Root** (Entidad principal).
* Diferencia correctamente los Value Objects de las Entidades Locales. Si un concepto tiene identidad propia (UUID), ciclo de vida individual y atributos que mutan con el tiempo, modélalo como una Entidad local dentro del Agregado, no como un Value Object inmutable.
* **Rich Domain Model:** Las entidades deben proteger sus invariantes desde que nacen. Usa constructores estandarizados y no permitas la creación de objetos en estados inválidos.

#### 4. Arquitectura de Identificadores (Doble ID):
Implementa una separación estricta entre identidad de sistema e identidad de negocio basándote en esta regla:
* **System IDs (Para Base de Datos):** TODAS las entidades (Agregados y Entidades Locales) deben tener un Value Object para su ID que genere y valide un UUID v4 puro (ej. `123e4567-...`). No uses prefijos aquí.
* **Business IDs (Natural Keys para Humanos):** Se crean ÚNICAMENTE para Aggregate Roots que requieren trazabilidad humana (es decir, entidades que interactúan directamente con usuarios, que se usan para rastrear trámites por teléfono/correo, o que se imprimen en documentos formales/PDFs). Ejemplos: `NumeroSolicitud` o `NumeroPrestamo`. Este VO debe generar/validar un string con un prefijo semántico corto más caracteres alfanuméricos (ej. `sol_A8B39C`).
* *Excepción:* Entidades de uso puramente interno (ej. un ítem dentro de una solicitud) NO deben tener Business IDs, se manejan únicamente con su System ID (UUID).

#### 5. Lógica de Negocio y Eventos:
* Toda la lógica de transición de estados debe residir en métodos descriptivos de la Entidad (evita setters anémicos).
* Define qué cambios de estado deben disparar **Eventos de Dominio** para la trazabilidad auditable del negocio.

---

### Entregable esperado:
Presenta un **Plan de Diseño Estructurado** que incluya:
1.  Diccionario de **Ubiquitous Language**.
2.  Estructura del **Aggregate Root, Entidades Locales y Value Objects** (destacando claramente la separación de System IDs y Business IDs según aplique).
3.  Mapa de **Métodos de Negocio** y sus invariantes.
4.  **Interfaces** (Repositories) y **Eventos de Dominio** a crear.

**¿Entendido? Analiza los insumos adjuntos y genera el plan.**

***
