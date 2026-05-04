# Prompt: Planificación de la Capa de Presentación (Presentation Layer)

**Contexto del Proyecto:**
Continuamos con el desarrollo del sistema modular en PHP bajo Clean Architecture y DDD. Las capas de Dominio, Aplicación e Infraestructura ya están definidas. Ahora necesitamos planificar la Capa de Presentación (Presentation Layer), que será la frontera pública de nuestro sistema. Esta capa se encarga exclusivamente de manejar el protocolo HTTP, las rutas y la comunicación con el cliente (API REST).

### Insumos proporcionados:
1.  **[Archivos de Aplicación (Use Cases)]: (ESTRICTOS)** Las clases Handler, Inputs (DTOs) y Outputs de la capa de Aplicación. La Presentación solo puede interactuar con el sistema a través de estos.
2.  **[Archivo .feature]: (CONTEXTO)** Para entender los flujos de interacción del usuario y qué datos se espera recibir y devolver en las peticiones.

---

### Tu Tarea:
Actúa como un **Arquitecto de Software y Experto en APIs REST**. Debes generar un Plan de Implementación Detallado ÚNICAMENTE para la Capa de Presentación del módulo correspondiente. **No escribas código final todavía**, preséntame el plan. NO modifiques las capas de Dominio, Aplicación ni Infraestructura bajo ninguna circunstancia.

---

### Directrices Estrictas de la Capa de Presentación:

#### 1. Responsabilidad de la Capa (Solo HTTP):
*   Esta capa es un detalle de entrega. Su único trabajo es recibir peticiones HTTP, validarlas superficialmente, empaquetarlas, enviarlas a la Capa de Aplicación, y devolver una respuesta HTTP formateada.
*   **CERO Lógica de Negocio.** Los Controladores deben ser extremadamente delgados.

#### 2. Validaciones Perimetrales (Form Requests):
*   Toda validación de formato, tipos de datos, obligatoriedad y reglas simples (ej. email válido, string máximo 255 chars) debe hacerse en Form Requests (o equivalentes del framework) antes de tocar el Controlador.
*   **NO** valides reglas de negocio complejas aquí (ej. "el saldo debe ser suficiente"); eso le pertenece al Dominio.

#### 3. Controladores Orquestadores:
El flujo estricto de un Controlador debe ser:
*   Inyectar el Handler de Aplicación (Use Case) correspondiente.
*   Recibir el Request validado.
*   Mapear los datos del Request hacia el Input (DTO) de solo lectura esperado por el Handler.
*   Ejecutar el Handler y recibir el Output.
*   Retornar el Output utilizando un transformador de respuesta.

#### 4. Transformación de Respuestas (API Resources):
*   Nunca devuelvas los objetos internos directamente. Utiliza API Resources (o Transformers) para formatear el Output de la capa de Aplicación hacia un JSON estandarizado.
*   Asegúrate de usar los códigos de estado HTTP correctos (200 OK, 201 Created, 422 Unprocessable Entity, etc.).

#### 5. Rutas (Endpoints):
*   Diseña las rutas siguiendo convenciones RESTful utilizando sustantivos en plural y los verbos HTTP adecuados (GET, POST, PUT, PATCH, DELETE).

---

### Entregable esperado:
Presenta un **Plan de Diseño Estructurado** que incluya:
1.  **Estructura de directorios** a crear (Controllers, Requests, Resources, Routes).
2.  **Diseño de Rutas (Endpoints):** Listado de verbos HTTP, URIs y Controladores asignados.
3.  **Form Requests:** Listado de reglas de validación (seudocódigo o array de reglas) requeridas para los endpoints de mutación.
4.  **Controladores (Seudocódigo):** Un ejemplo del flujo interno de un método del Controlador (recibir request, mapear DTO, llamar handler, retornar resource).
5.  **API Resources:** Estructura del JSON de respuesta esperado.

**¿Entendido? Analiza los insumos y genera el plan de Presentación.**

