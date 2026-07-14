# CLAUDE.md — Hub Digital

## 1. Núcleo Arquitectónico (Clean Architecture & nwidart)

El proyecto usa nwidart/laravel-modules. Cada módulo es un Bounded Context independiente.

* **Módulos:** LoanReceptionManagement, InventoryCollectionManagement, PublicCatalog (único con Laravel AI SDK).
* **Regla de Oro:** Prohibido importar clases del Domain/ de otro módulo. Si PublicCatalog requiere datos de inventario, usa un Port en su aplicación implementado por un adaptador en infraestructura que llame al repositorio de inventario.

### Estructura Interna Obligatoria (Modules//app/)

* Domain/: Entidades, ValueObjects (final readonly, constructor privado, factory estático), Services (puros, sin estado), Events, Repositories (**Interfaces únicamente**). *Sin dependencias de Laravel o Carbon (usar DateTimeImmutable).*
* Application/: UseCases// (Contiene exclusivamente Handler.php [sufijo obligatorio], Input.php y Output.php). Ports/ (Interfaces de servicios externos).
* Infrastructure/: Persistence/Eloquent/Models/ (EloquentModel), Persistence/Eloquent/Repositories/, Providers/ (ServiceProvider.php), Gateways/, Notifications/.
* Presentation/: Http/Controllers/ (Livewire/API), Requests/ (toInput()), Resources/.

### Registro de Dependencias y Rutas

* Toda inyección Interface $\rightarrow$ Implementación se declara estrictamente en el array $bindings de app/Infrastructure/Providers/ServiceProvider.php. No uses AppServiceProvider.
* Las migraciones viven en database/migrations/ del módulo y se cargan con $this->loadMigrationsFrom(module_path($this->name, 'database/migrations')) en el boot() del proveedor.
* Las rutas viven en routes/web.php o api.php del módulo. Nunca en la raíz del proyecto.

---

## 2. Convenciones de Behat (BDD)

* **Ubicación:** Modules//tests/Behat/Features//<nombre_escenario>.feature y Contexts//Context.php (Espejo estricto).
* **Restricciones de Estructura:** Un archivo .feature = una funcionalidad cohesiva. **Un Context por archivo feature**. BaseContext.php contiene **únicamente** el bootstrap de Laravel y el reset de la base de datos (migrate:fresh en @BeforeScenario).

### Flujo de Desarrollo BDD Obligatorio

```
Pest/Unit (Reglas dominio aisladas sin DB) 
  ↳ Scaffold Behat (php artisan behat:scaffold <M> <C> <F>) 
  ↳ Generar Esqueleto (Entidad -> Interfaz Repo -> Handler -> Migración -> Repo Eloquent -> Binding) 
  ↳ Implementar Pasos Context 
  ↳ Behat Green ✅ 
  ↳ Completar Lógica Handler (Eventos, invariantes) 
  ↳ Pest/Integration (Probar repositorio con DB) 
  ↳ Frontend/UI

```

### Reglas de Gherkin

* Primera línea obligatoria: # language: es. Todo el archivo se escribe en **Español**.
* **Actores:** Cada Escenario debe nombrar al actor en el título. Prohibido usar "el usuario".
* *LoanReceptionManagement:* el investigador, el curador
* *InventoryCollectionManagement:* el curador, el sistema, el visitante habilitado (solo reubicación, habilitado vía URL firmada por QR)
* *PublicCatalog:* el visitante, el investigador


* **Pasos:** Un solo Cuando por escenario. Prohibido Y o Pero al inicio de un bloque. Los datos variables van en tablas (Ejemplos:), nunca en el título.
* **Interacción del Contexto:** Dado siembra estado usando interfaces del Repositorio de dominio o un Handler de configuración (Nunca Eloquent directamente). Cuando ejecuta el Handler del caso de uso envuelto en un try/catch. Entonces evalúa las aserciones sobre $this->ultimaRespuesta o $this->excepcionCapturada.
* **Estado:** El estado entre pasos se guarda en propiedades de instancia (reajustables por escenario), nunca en variables estáticas ni en el contenedor de Laravel.

---

## 3. Frontend & Diseño UI (Flux UI & Tailwind)

* **Sistema de Color:** Prohibido usar Hex en las vistas. Usar tokens de resources/css/app.css:
* *Primarios:* bg-blue-navy (#1B365D), bg-bio-green (#2E7D32), bg-science-blue (#1976D2).
* *Semánticos:* success (#4CAF50), warning (#FF9800), error (#D32F2F), info (#0288D1).
* *Neutros:* Fondo de página bg-bg-main (#F5F7FA), tarjetas bg-surface (#FFFFFF), texto text-text-primary.


* **Tipografía:** Tamaños siempre en rem. Títulos H1/H2 y nombres científicos en cursiva usan font-serif (Roboto Slab). Interfaz, tablas y textos comunes usan font-sans (Inter).
* **Componentes:** Iconos únicamente <flux:icon name="..." /> en variante **outline**. Bordes estándar rounded-lg (8px). Sombras solo en tarjetas (shadow-sm).
* **Arquitectura de Vistas:** Las vistas específicas viven en Modules//resources/views/. Solo se promueven a la raíz resources/views/components/ si son compartidas por más de un módulo. Todo link interno lleva wire:navigate. Los componentes Livewire son capa de presentación: inyectan el Handler, arman el Input DTO y renderizan basándose en el Output DTO ($\le 10$ líneas por acción).

### Heurísticas Críticas de Usabilidad

1. **Estado del Sistema IoT:** Siempre visible en el header con un indicador visual (bg-success / Online).
2. **Prevención de Errores:** Formularios taxonómicos deben usar autocompletado en base a datos reales (<flux:input list="taxa-list">), nunca texto libre puro.
3. **Reconocimiento:** Mostrar siempre el nombre legible por humanos al referenciar entidades (Ej: Box A1 junto a su ID).
4. **Accesibilidad en Laboratorio:** Diseño mobile-first optimizado para móviles. Áreas de toque de botones/escaners $\ge 44\times44\text{px}$.
5. **Tablas responsivas (patrón obligatorio):** Toda tabla de datos se renderiza con **dos bloques** alimentados por el mismo bucle: la `<table>` envuelta en `hidden md:block` + `overflow-x-auto` para escritorio, y una lista de **tarjetas apiladas** (`md:hidden`) para móvil — nunca scroll horizontal forzado en móvil. El par etiqueta/valor de cada tarjeta usa el componente `seguimiento-fisico.campo-movil`. Padding raíz de página `p-4 sm:p-6`; encabezado con acción `flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between` y botón `w-full sm:w-auto`. Los botones de acción dentro de tarjetas móviles no usan `size="sm"` (deben respetar el toque $\ge 44\text{px}$).

---

## 4. Comandos de Verificación Rápidos

* **Formateador:** vendor/bin/pint --dirty --format agent (Obligatorio antes de confirmar cambios).
* **Ejecutar Behat (todo, local):** vendor/bin/behat --suite=
* **Ejecutar Behat como el pipeline:** vendor/bin/behat --profile=default --tags=@listo --strict

### Tag @listo (features que corren en CI)

* El pipeline (.github/workflows/tests.yml) **solo corre las features etiquetadas con `@listo`** (`--tags=@listo --strict`). Es opt-in: una feature sin el tag no rompe CI aunque esté a medias (pasos pending/undefined o escenarios en rojo).
* **Cuando una feature queda terminada y 100% verde**, agrega `@listo` en la línea inmediatamente encima de `Característica:` (después del `# language: es`). A partir de ahí entra sola al pipeline.
* Por ser un set curado se corre en `--strict`: una feature `@listo` **debe** estar totalmente verde, sin pasos pending ni undefined. No marques una feature como `@listo` si no pasa local con `--tags=@listo --strict`.

See local notes: @claude.local.md

## OBLIGACIONES

- **NUNCA TIENES PERMITIDO EJECUTAR DB:FRESH O PARECIDO PARA REINICIAR LA BD, LA INFORMACIÓN ES VITAL QUE SE PRESERVE**
