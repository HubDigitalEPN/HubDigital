> **Prompt: Planificación del Frontend / Capa de Presentación Web (TALL Stack, Flux UI & Traducción Visual)**
>
> ---
> > **⚠️ Nota sobre la arquitectura de capas:**
> > En proyectos que usan **Livewire como canal principal**, este prompt reemplaza a `Prompt-Capa-Presentacion.md`.
> > El componente Livewire cumple simultáneamente el rol de **Presentación** (llama Handlers, valida input, gestiona estado) y de **Frontend** (renderiza la vista Blade con Flux UI).
> > Usa `Prompt-Capa-Presentacion.md` únicamente cuando el canal de salida sea una **API REST** (Postman, app móvil, integración externa).
> ---
>
> **Contexto del Proyecto:**
> Estamos construyendo la interfaz de usuario de nuestro sistema modular (Hub Digital) utilizando estrictamente el **TALL Stack (Tailwind CSS, Alpine.js, Laravel, Livewire)** y la librería de componentes **Flux UI**. Ya existe una arquitectura backend definida (Clean Architecture/DDD) y contamos con Guidelines estrictos de Frontend que especifican paletas de colores, componentes base de Flux y pasos de implementación.
>
> **Insumos proporcionados:**
> 1. **[Endpoints/Casos de Uso]: (ESTRICTOS)** Los contratos de entrada y salida (DTOs/JSON) definidos por la capa de Presentación/Aplicación.
> 2. **[Guidelines de Frontend]:** El documento de estándares que detalla las paletas, tipografías, el uso de Flux UI y la estructura de archivos.
> 3. **[Archivo .feature]: (CONTEXTO)** Para entender la experiencia y el flujo de pantallas que el usuario espera navegar según BDD.
> 4. **[Carpeta /references]: (REFERENCIA ESTRICTAMENTE VISUAL)** Contiene prototipos de diseño creados con tecnologías ajenas a nuestro ecosistema (HTML puro, JSX, React, etc.).
>
> ---
>
> **Tu Tarea:**
> Actúa como un **Arquitecto Frontend Experto en TALL Stack**. Debes generar un Plan de Implementación Detallado para las pantallas y componentes Livewire del módulo correspondiente. **No escribas código final todavía**, preséntame el plan.
>
> **ADVERTENCIA CRÍTICA:** Bajo ninguna circunstancia debes inventar paletas de colores, usar librerías JS externas, ni alterar los pasos de arquitectura de los Guidelines. Además, **tienes estrictamente prohibido utilizar o sugerir las tecnologías base de los prototipos en `/references` (nada de React, JSX, o arquitecturas ajenas a Blade)**.
>
> ---
>
> **Directrices Estrictas del Frontend:**
>
> 1. **Traducción Visual del Prototipo:**
     >    * Analiza los prototipos en la carpeta `/references` para extraer **únicamente** la disposición de los elementos, el flujo visual, la estructura de columnas/tarjetas y la experiencia de usuario (UX).
>    * Debes "traducir" ese diseño visual utilizando pura y exclusivamente los componentes de **Flux UI**, **Blade** y clases de **Tailwind CSS** definidas en nuestros Guidelines.
>
> 2. **Adhesión Estricta a los Guidelines y Flux UI:**
     >    * Usa ÚNICAMENTE los tokens de diseño (clases de Tailwind) y los componentes prefabricados de Flux UI especificados. No uses CSS personalizado ni códigos hex arbitrarios.
>    * **⚠️ Tablas Flux UI — padding obligatorio:** `flux:table` aplica internamente `first:ps-0` a la primera `<th>` y `<td>` de cada fila, eliminando el padding izquierdo. Para evitar que el texto quede pegado al borde debes:
>      1. Agregar `class="!ps-4"` en el primer `<flux:table.column>` del encabezado.
>      2. Agregar `class="!ps-4 px-4 py-3"` en el primer `<flux:table.cell>` de cada fila.
>      3. Agregar `class="px-4 py-3"` en todos los demás `<flux:table.cell>` y `<flux:table.column>`.
>      4. Envolver el componente `<flux:table>` en un `<div class="p-6 ...">` (o asegurarte de que el wrapper externo de la vista tenga `p-6`) para que exista separación entre la tabla y los bordes de la tarjeta.
>
> 3. **Convención de Capitalización en Español:**
    * En **toda** la interfaz, los textos en español siguen la regla: **solo la primera palabra de cada frase va en mayúscula**. El resto de palabras van en minúscula, salvo nombres propios o siglas.
    * Ejemplos correctos: `Bandeja de solicitudes`, `Línea de investigación`, `Guardar borrador`, `Ver mis solicitudes`, `Código de espécimen`.
    * Ejemplos incorrectos: `Bandeja de Solicitudes`, `Línea de Investigación`, `Guardar Borrador`.
    * Esto aplica sin excepción a: títulos de página (`<flux:heading>`), etiquetas de formulario (`<flux:label>`), encabezados de tabla (`<flux:table.column>`), etiquetas de datos (`<dt>`), textos de botones, breadcrumbs y modales.
    * Los elementos con `uppercase` en CSS (ej. secciones del acta) también deben tener el texto fuente en minúscula (el CSS se encarga de la transformación visual).

 4. **Integración Livewire - Backend:**
     >    * Diseña los Componentes Livewire (Full-page o anidados) que consumirán los endpoints o interactuarán con los Casos de Uso del backend.
     >    * Planifica el manejo de los estados (carga, éxito, errores). Para los errores de validación de backend (422), confía en el manejo nativo de `$errors` de Livewire/Blade.
     >    * **PROHIBIDO (regla heredada de la capa de Presentación, válida también con Livewire):** acceso directo a Base de Datos / Eloquent / `Model::query()` en el componente, **incluido `render()` y `mount()`**. Tampoco inyectes interfaces de `Domain/Repositories/` en el componente. **Toda** lectura o escritura pasa por un **Handler** de Application que recibe un Input DTO y devuelve un Output DTO. Esto aplica a pantallas de **lectura/listado/detalle/formulario**, no solo a las acciones de escritura — "solo estoy trayendo datos para mostrar" **NO** es una excepción.
     >    * **Pantallas de listado/detalle (bandejas, tablas filtrables):** el filtrado, orden y búsqueda van en un **query Handler** (Handler + Input + Output con DTOs-fila `final readonly`), no en `render()`. El componente solo arma el Input desde sus propiedades públicas y entrega el Output a la vista. Sigue el patrón read-side documentado en `.ai/skills/laravel-clean-arch/references/eloquent-as-infrastructure.md` (sección "Query interface") y la sección §7 de `.ai/guidelines/clean-architecture.md`.
     >    * **Blade nunca toca Eloquent:** las plantillas solo iteran colecciones de DTOs ya resueltas por el Handler (propiedades camelCase del DTO), jamás ejecutan consultas ni acceden a modelos. Expón fechas como `DateTimeImmutable` en el DTO y compara con `$x < now()` en Blade (no uses `->isPast()` de Carbon).

5. **Estructura de Componentes Blade/Livewire:**
     >    * Desglosa la interfaz en componentes lógicos (Páginas Livewire, Layouts Blade, Componentes anónimos Blade) inspirándote en la estructura del prototipo visual, pero adaptándola a nuestro stack.
     >    * Reutiliza los componentes globales ya documentados siempre que sea posible. Usa Alpine.js SOLAMENTE para interactividad puramente visual (modales, dropdowns, toggles) para evitar viajes innecesarios al servidor.

6. **Gestión del Estado:**
     >    * Define las propiedades públicas (`public properties`) de los componentes Livewire que representarán el estado local. Explica cómo se mantendrá la reactividad sin sobrecargar el servidor.
     >    * **PROHIBIDO almacenar un modelo Eloquent en una propiedad pública** (ej. `public ?SolicitudPrestamoModel $solicitud`). Livewire **serializa** las propiedades públicas entre requests y un modelo Eloquent es frágil (se re-hidrata, pierde relaciones, expone columnas de BD). Guarda solo escalares o **DTOs de lectura** (`final readonly`); si la pantalla necesita el detalle completo, reconstrúyelo en `render()` invocando el query Handler.

7. **Responsive Design Obligatorio:**
     >    * El diseño de esta pantalla y de **todas** las pantallas del módulo correspondiente debe planificarse como responsive desde el inicio.
     >    * Define cómo se adaptará cada vista a móvil, tablet y escritorio usando únicamente Flux UI, Blade y Tailwind CSS, sin introducir CSS personalizado ni librerías externas.
     >    * Prioriza una experiencia mobile-first y explica qué bloques se apilan, colapsan, ocultan o reordenan en los distintos breakpoints.

---

**Entregable esperado:**
Presenta un **Plan de Diseño Estructurado** que incluya:
1. **Mapa de Componentes:** Listado de los archivos a crear (`.php` de Livewire y `.blade.php`), referenciando qué componentes de Flux UI consumirán para replicar la estructura de la carpeta `/references`.
2. **Integración y Flujo de Datos:** Explicación de cómo los métodos del componente Livewire llamarán a la capa de Aplicación/API y mapearán los datos a las propiedades públicas.
3. **Flujo de Estados de UI:** Cómo se representarán visualmente los estados de `loading` (ej. `wire:loading`), `success` y `error` usando estrictamente los elementos de los Guidelines.
4. **Mapeo de UI vs. Feature:** Una breve correlación demostrando cómo los pasos del `.feature` se satisfacen con las pantallas planificadas.
5. **Responsividad por pantalla:** Una descripción breve de cómo cada pantalla del módulo se adapta en móvil, tablet y escritorio, indicando cambios de layout, densidad de información y comportamiento de componentes.

> **¿Entendido? Analiza los insumos, LEE LOS GUIDELINES, extrae la esencia de /references y genera el plan de Frontend.**
