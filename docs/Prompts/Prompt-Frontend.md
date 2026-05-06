> **Prompt: Planificación del Frontend (TALL Stack, Flux UI & Traducción Visual)**
>
> **Contexto del Proyecto:**
> Estamos construyendo la interfaz de usuario de nuestro sistema modular (Hub Digital) utilizando estrictamente el **TALL Stack (Tailwind CSS, Alpine.js, Laravel, Livewire)** y la librería de componentes **Flux UI**. Ya existe una arquitectura backend definida (Clean Architecture/DDD), la API está planificada y ya contamos con Guidelines estrictos de Frontend que especifican paletas de colores, componentes base de Flux y pasos de implementación.
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
>
> 3. **Integración Livewire - Backend:**
     >    * Diseña los Componentes Livewire (Full-page o anidados) que consumirán los endpoints o interactuarán con los Casos de Uso del backend.
>    * Planifica el manejo de los estados (carga, éxito, errores). Para los errores de validación de backend (422), confía en el manejo nativo de `$errors` de Livewire/Blade.
>
> 4. **Estructura de Componentes Blade/Livewire:**
     >    * Desglosa la interfaz en componentes lógicos (Páginas Livewire, Layouts Blade, Componentes anónimos Blade) inspirándote en la estructura del prototipo visual, pero adaptándola a nuestro stack.
>    * Reutiliza los componentes globales ya documentados siempre que sea posible. Usa Alpine.js SOLAMENTE para interactividad puramente visual (modales, dropdowns, toggles) para evitar viajes innecesarios al servidor.
>
> 5. **Gestión del Estado:**
     >    * Define las propiedades públicas (`public properties`) de los componentes Livewire que representarán el estado local. Explica cómo se mantendrá la reactividad sin sobrecargar el servidor.
>
> ---
>
> **Entregable esperado:**
> Presenta un **Plan de Diseño Estructurado** que incluya:
> 1. **Mapa de Componentes:** Listado de los archivos a crear (`.php` de Livewire y `.blade.php`), referenciando qué componentes de Flux UI consumirán para replicar la estructura de la carpeta `/references`.
> 2. **Integración y Flujo de Datos:** Explicación de cómo los métodos del componente Livewire llamarán a la capa de Aplicación/API y mapearán los datos a las propiedades públicas.
> 3. **Flujo de Estados de UI:** Cómo se representarán visualmente los estados de `loading` (ej. `wire:loading`), `success` y `error` usando estrictamente los elementos de los Guidelines.
> 4. **Mapeo de UI vs. Feature:** Una breve correlación demostrando cómo los pasos del `.feature` se satisfacen con las pantallas planificadas.
>
> **¿Entendido? Analiza los insumos, LEE LOS GUIDELINES, extrae la esencia de /references y genera el plan de Frontend.**
