# Prompt: Auditoría de Violaciones de Clean Architecture (Plan de Remediación)

## Contexto del Proyecto
Sistema modular en PHP (Laravel + nwidart/laravel-modules) bajo **Clean Architecture + DDD**. Cada módulo es un Bounded Context con la estructura `Modules/<Módulo>/app/{Domain,Application,Infrastructure,Presentation}`. La regla de dependencias es estricta: **las flechas apuntan hacia adentro** (Presentation → Application → Domain; Infrastructure implementa los puertos de Domain/Application). Eloquent, Carbon y los facades de Laravel son **detalles de Infrastructure**.

Las guidelines y skills de referencia son la fuente de verdad:
- `.ai/guidelines/clean-architecture.md` (especialmente la tabla de Presentation y la §7 — Livewire/lectura/Blade/props públicas).
- `.ai/guidelines/module-structure.md`
- `.ai/skills/laravel-clean-arch/SKILL.md` y `references/eloquent-as-infrastructure.md` (patrón read-side: "Query interface" → DTOs).
- `CLAUDE.md` (Núcleo Arquitectónico y Regla de Oro de no importar `Domain/` de otro módulo).

> **ADVERTENCIA CRÍTICA:** Este prompt es **solo de auditoría y planificación**. NO escribas código final, NO edites archivos del módulo, NO ejecutes formateadores/tests/migraciones. NUNCA `db:fresh` ni nada que reinicie la BD. El entregable es un **Plan de Remediación**.

---

## Insumos Proporcionados
* **[Módulo o flujo a auditar]: (OBLIGATORIO)** Nombre del módulo (ej. `InventoryCollectionManagement`) y, si aplica, el sub-flujo concreto (ej. "préstamos", "depósitos"). Define el **alcance**.
* **[Flujos fuera de alcance]: (OPCIONAL)** Sub-flujos del mismo módulo a excluir explícitamente (para no mezclar trabajo).
* **[Guidelines/Skills]: (CONTEXTO)** Los archivos `.ai/` listados arriba.

---

## Tu Tarea
Actúa como un **Arquitecto de Software experto en Clean Architecture y DDD sobre Laravel**. Audita el módulo/flujo indicado, **clasifica cada violación encontrada**, y produce un **Plan de Remediación priorizado**. Trabaja en dos fases:

1. **Descubrimiento (solo lectura):** localiza evidencias con búsquedas dirigidas, lee los archivos sospechosos y confirma cada hallazgo (evita falsos positivos: comentarios en docblocks, valores de filtro tipo `value="numero_solicitud"`, claves de arrays que alimentan DTOs, etc.).
2. **Plan:** agrupa por caso, asigna severidad, cita evidencia (`archivo:línea`) y propone la corrección reutilizando los patrones ya establecidos en el proyecto.

No asumas la estructura de los DTOs/Handlers: **inspecciona los archivos reales** antes de proponer.

---

## Catálogo de Violaciones a Detectar

### Casos conocidos (confirmados en auditorías previas)
- **Caso A — Presentation → Eloquent/Jobs.** Componentes Livewire o controladores que usan `*EloquentModel`, `::query()`, `Model::find/create/where`, o despachan Jobs directamente. **Incluye `render()` y `mount()`**, no solo las acciones de escritura. "Solo traigo datos para mostrar" NO es excepción.
- **Caso B — Presentation → Repositorio de Domain.** Componentes que inyectan/usan una interfaz de `Domain/Repositories/` directamente, saltándose la capa de Application. Debe pasar por un Handler.
- **Caso C — Cross-module Domain.** Cualquier capa de un módulo que importe `Modules\<OtroMódulo>\Domain\...`. Lo permitido es un **Port** (en Application) implementado por un **adapter** (en Infrastructure) contra un contrato publicado del otro módulo. Reportar incluso si está "detrás de un adapter" pero importa Domain Services/Entidades del otro módulo.
- **Caso D — Eloquent en Blade.** Plantillas `.blade.php` que ejecutan consultas, acceden a modelos Eloquent o leen columnas snake_case en vez de propiedades camelCase de un DTO.

### Casos adicionales a evaluar (amplía la búsqueda)
- **E — Domain contaminado:** `use Illuminate\*`, `Carbon`, `App\Models\*`, facades, o helpers (`now()`, `auth()`, `request()`, `config()`, `app()`) dentro de `Domain/`. El dominio usa `DateTimeImmutable`.
- **F — Application con detalles de framework:** `DB::`/`Schema::`, facades (`Storage`, `Mail`, `Http`, `Log`, `Notification`, `Cache`, `Queue`, `Event`), `Carbon::`, `now()`, `auth()`, `request()` en `Application/`. Lo externo va detrás de un **Port**. La atomicidad va por `TransactionManagerPort`, nunca `DB::transaction()`.
- **G — Fugas de tipos del Domain hacia afuera:** Handlers que **devuelven Entidades o Value Objects** en vez de un Output DTO; o que reciben/retornan modelos Eloquent.
- **H — Estructura de Use Case:** carpetas `UseCases/<Nombre>/` que no tengan exactamente `Handler` + `Input` + `Output`; sufijo `Handler` ausente; Input/Output que no sean `final readonly`.
- **I — Puertos/Repos mal definidos:** clases (no interfaces) en `Application/Ports/`; interfaces de repositorio fuera de `Domain/Repositories/`; modelos Eloquent fuera de `Infrastructure/Persistence/Eloquent/Models/`; nombres que no sean `<Entidad>EloquentModel`.
- **J — Bindings ausentes:** interfaces `Port`/`Repository` sin su binding en el `ServiceProvider` del módulo; uso de `app()->bind()` fuera del ServiceProvider o de `AppServiceProvider` para bindings del módulo.
- **K — Estado Livewire frágil:** propiedades públicas que almacenan modelos Eloquent (riesgo de serialización) o entidades/VOs del dominio.
- **L — Reglas de negocio en Presentation/Blade:** condicionales de negocio, cálculos de invariantes o validaciones de dominio en el componente o en `@php` de la vista.
- **M — Rutas/migraciones mal ubicadas:** rutas fuera de `routes/{web,api}.php` del módulo; migraciones fuera de `database/migrations/` del módulo.

---

## Búsquedas Dirigidas Sugeridas (solo lectura, acotar al módulo)
> Ajusta rutas al módulo auditado. Filtra los flujos fuera de alcance. Verifica manualmente cada match (descarta comentarios, valores de `<select.option>`, claves de arrays para DTOs).

```
# A — Eloquent/Jobs en Presentation
grep -rn 'EloquentModel|::query(|::find(|::create(|->dispatch(|dispatch(' Modules/<Mod>/app/Presentation

# B — Repos de Domain inyectados en Presentation
grep -rn 'RepositoryInterface' Modules/<Mod>/app/Presentation

# C — Imports cross-module
grep -rn 'use Modules\\' Modules/<Mod>/app | grep -v 'use Modules\\<Mod>\\'

# D — Eloquent / snake_case / isPast en vistas
grep -rn 'EloquentModel|::query(|->isPast()' Modules/<Mod>/resources/views

# E — Domain contaminado
grep -rn 'use Illuminate|use Carbon|use App\\Models|Carbon::|now()|auth()|request()' Modules/<Mod>/app/Domain

# F — Application con framework
grep -rn 'DB::|Schema::|Storage::|Mail::|Http::|Log::|Notification::|Carbon::|now()|auth()|request()|app(' Modules/<Mod>/app/Application

# G — Handlers que devuelven Entidad/VO (revisar firmas de retorno y `return $entidad`)
# H/I — estructura: listar UseCases/*, Ports/*, Repositories/*, Models/*
# J — contrastar interfaces contra el array $bindings del ServiceProvider del módulo
```

---

## Patrones de Remediación a Proponer (reutilizar lo existente)
- **Lectura/listado/detalle (A, B, D):** crear un **query Handler** (`Consultar<Algo>/` con `Handler` + `Input` + `Output` y DTOs-fila `final readonly`). El filtrado/orden/búsqueda baja al **repositorio** (método de lectura que devuelve arrays/DTOs planos, fechas como `DateTimeImmutable`) siguiendo el read-side de `eloquent-as-infrastructure.md`. El componente arma el Input y entrega el Output (≤10 líneas por acción). La vista itera DTOs camelCase.
- **Resolución de datos auxiliares (ej. nombre de usuario por id):** vía **Port** + adapter (no `User::find` en Presentation).
- **Cross-module (C):** **Port** en `Application/Ports/` del módulo consumidor + adapter en `Infrastructure/` que hable con un contrato publicado del otro módulo (no su `Domain/`).
- **Application (F):** mover el efecto externo a un **Port**; atomicidad con `TransactionManagerPort`.
- **Estado Livewire (K):** reemplazar modelos públicos por escalares o DTOs; reconstruir el DTO en `render()`.

---

## Entregable Esperado: Plan de Remediación
Presenta un **plan estructurado y escaneable**:

1. **Alcance auditado:** módulo/flujo, qué se incluyó y qué se excluyó explícitamente.
2. **Resumen ejecutivo:** tabla con conteo de hallazgos por caso (A–M) y veredicto general (¿limpio / violaciones menores / violaciones estructurales?).
3. **Hallazgos detallados:** por cada violación: **Caso**, **severidad** (Alta = depende de infra o salta capas en escritura / Media = lectura o capa saltada / Baja = nombre/estructura), **evidencia** (`archivo:línea` + fragmento mínimo), y **por qué viola** la regla (citando la guideline).
4. **Plan de corrección dividido en TANDAS (lotes) incrementales.** No entregues un plan monolítico: parte el trabajo en tandas pequeñas y secuenciales, cada una **autocontenida, verificable y commiteable por separado**, para que pueda ejecutarse en sesiones distintas (incluso por personas distintas) sin perder el hilo. Reglas para las tandas:
   - **Criterio de corte:** agrupa por **subgrupo de pantallas/archivos cohesivo** (ej. "todas las bandejas del curador", "los 3 detalles de préstamo") o por caso cuando sea más natural. Una tanda debería ser abarcable en una sesión y dejar el módulo en estado consistente (compila, sin romper pantallas).
   - **Orden entre tandas:** primero lo que **desbloquea** o se reutiliza después (ej. crear un Port id→nombre, o un método de lectura de repositorio compartido, antes de las pantallas que lo consumen); luego por severidad/riesgo (normalmente D → A → B → resto).
   - **Contenido de cada tanda:** (a) **nombre y objetivo**; (b) **archivos afectados** (componentes + vistas juntos); (c) **artefactos nuevos** (Handlers/DTOs/métodos de repositorio/Ports/bindings); (d) **dependencias** de tandas previas; (e) **definición de hecho** (grep objetivo en CLEAN + `php -l` OK + pantalla funciona); (f) estimación gruesa de tamaño (S/M/L).
   - Para patrones repetidos, descríbelos **una vez** ("plantilla") y la primera tanda que lo aplique sirve de referencia; las siguientes solo dicen "replica el patrón de la Tanda N". No enumeres cada línea ni cada archivo si son muchos: usa rutas representativas.
   - Incluye una **tabla resumen de tandas** (N.º, objetivo, casos que cubre, archivos aprox., dependencias, tamaño) para ver el roadmap de un vistazo.
5. **Falsos positivos descartados:** lista breve de matches que NO son violaciones y por qué (para dar confianza en la auditoría).
6. **Excepciones aceptadas:** violaciones que se dejan a propósito (ej. un Caso C decidido por el usuario), documentadas como tales.
7. **Verificación post-remediación:** comandos de grep que deberían quedar en CLEAN y `php -l` de los archivos tocados, **por tanda y globales**. (El usuario corre pint/behat él mismo.)

> **Autosuficiencia obligatoria:** el plan debe poder ejecutarlo alguien que **no participó en esta auditoría** y solo tiene el módulo, las guidelines `.ai/` y este plan. No asumas contexto previo: nombra archivos por ruta, cita la regla violada y describe el patrón completo la primera vez. Si dejas trabajo para después, que quede explícito en qué tanda y bajo qué condición se retoma.

> **¿Entendido? Acota el alcance, LEE las guidelines, ejecuta las búsquedas dirigidas, confirma cada hallazgo leyendo el archivo, y entrega el Plan de Remediación dividido en tandas. No escribas código final ni edites el módulo.**
