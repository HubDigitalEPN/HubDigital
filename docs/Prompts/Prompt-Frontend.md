# Prompt: Planificación del Frontend

**Contexto del Proyecto:**
Estamos construyendo la interfaz de usuario que consumirá la API de nuestro sistema modular. Ya existe una arquitectura definida, la API está planificada y, lo más importante, **ya contamos con Guidelines estrictos de Frontend que especifican paletas de colores, componentes base y pasos de implementación.**

### Insumos proporcionados:
1.  **[Endpoints de la API]: (ESTRICTOS)** Los contratos de entrada y salida (JSON) definidos por la capa de Presentación.
2.  El documento de estándares que detalla las paletas, tipografías, componentes UI reutilizables y la estructura de archivos que debe usarse ya está en tu contexto, apégate exclusivamente a lo que ahí se define.
3.  **[Archivo .feature]: (CONTEXTO)** Para entender la experiencia y el flujo de pantallas que el usuario espera navegar.

---

### Tu Tarea:
Actúa como un **Líder Técnico de Frontend**. Debes generar un Plan de Implementación Detallado para las pantallas y componentes del módulo correspondiente. **No escribas código final todavía**, preséntame el plan. **ADVERTENCIA CRÍTICA:** Bajo ninguna circunstancia debes inventar paletas de colores, estilos nuevos, ni alterar los pasos de arquitectura ya definidos en los Guidelines proporcionados.

---

### Directrices Estrictas del Frontend:

#### 1. Adhesión Estricta a los Guidelines:
*   Usa ÚNICAMENTE los tokens de diseño (colores, espaciados, tipografías) especificados en los Guidelines. Si necesitas un color para un botón de error, usa la variable definida en el guideline, no un hex code arbitrario.
*   Sigue al pie de la letra la estructura de carpetas y los pasos de creación de componentes estipulados.

#### 2. Consumo de API (Servicios):
*   Diseña la capa de servicios o llamadas a red que se comunicarán con los endpoints de la API proporcionados.
*   Planifica el manejo de los estados HTTP (carga, éxito, errores de validación 422, errores de servidor 500) mapeándolos a componentes de UI existentes en el Guideline (ej. Toasts, Error Messages).

#### 3. Estructura de Componentes (Atomic Design / Estructura del Proyecto):
*   Desglosa la interfaz requerida en componentes lógicos siguiendo la jerarquía definida en el proyecto (Páginas, Layouts, Componentes inteligentes/tontos).
*   Reutiliza los componentes globales ya documentados siempre que sea posible en lugar de proponer componentes nuevos equivalentes.

#### 4. Gestión del Estado (State Management):
*   Define cómo se manejará el estado local (dentro del componente) y el estado global (si aplica) para los datos de este módulo, respetando las herramientas ya elegidas en los Guidelines.

---

### Entregable esperado:
Presenta un **Plan de Diseño Estructurado** que incluya:
1.  **Mapa de Componentes:** Listado de los archivos a crear (Páginas y Componentes específicos del módulo), referenciando qué componentes globales del Guideline consumirán.
2.  **Integración de API:** Definición de los métodos de servicio que harán fetch a los endpoints proporcionados, incluyendo el manejo esperado de los payloads.
3.  **Flujo de Estados de UI:** Cómo se representarán visualmente los estados de `loading`, `success` y `error` usando estrictamente los elementos de los Guidelines.
4.  **Mapeo de UI vs. Feature:** Una breve correlación demostrando cómo los pasos del `.feature` se satisfacen con las pantallas planificadas.

**¿Entendido? Analiza los insumos, LEE LOS GUIDELINES y genera el plan de Frontend.**