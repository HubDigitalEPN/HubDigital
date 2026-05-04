# Prompt: Planificación de la Capa de Presentación (Presentation Layer - API REST)

## Contexto del Proyecto
Continuamos con el desarrollo del sistema modular en PHP bajo Clean Architecture y DDD. Las capas de Dominio (Domain), Aplicación (Application) e Infraestructura (Infrastructure) ya están finalizadas y cerradas. Ahora necesitamos planificar la Capa de Presentación (Presentation Layer), que actuará exclusivamente como una API REST backend.

Para esta tarea, usa las guidelines correspondientes a Clean Architecture, Monolito Modular y las skills correspondientes.

> **ADVERTENCIA CRÍTICA:** Esta capa es estrictamente backend. **NO** debes proponer, diseñar ni mencionar nada relacionado con frontend, vistas Blade, Inertia, Vue, React, HTML o CSS. Toda la comunicación será mediante endpoints consumiendo y retornando JSON.

---

## Insumos Proporcionados

* **[Archivos de Application]: (OBLIGATORIOS)** Los Handlers de los Casos de Uso y sus Input/Output DTOs. El controlador debe saber qué datos requiere la aplicación para funcionar.
* **[Archivo .feature]: (CONTEXTO)** Para entender los flujos de interacción del usuario con el sistema, lo cual dictará la creación de las rutas HTTP y los verbos (GET, POST, PUT, DELETE) adecuados.

---

## Tu Tarea
Actúa como un Arquitecto de Software experto en Laravel y Diseño de APIs REST. Debes generar un Plan de Implementación Detallado **ÚNICAMENTE** para la Capa de Presentación. No escribas código final todavía, preséntame el plan (seudocódigo estructurado). **NO modifiques las capas inferiores bajo ninguna circunstancia.**

---

## Directrices Estrictas de la Capa de Presentación

### 1. Responsabilidad de la Capa (Controladores "Delgados")
El Controlador es solo un traductor HTTP. Su flujo estricto debe ser:
* Recibir la petición HTTP.
* Validar la estructura del request.
* Mapear los datos validados al Input DTO de la capa de Aplicación.
* Inyectar o invocar el Handler del Caso de Uso.
* Retornar el Output del Handler transformado en una respuesta JSON estandarizada.
* **PROHIBIDO:** Colocar lógica de negocio, reglas de dominio o acceso directo a Base de Datos/Eloquent en los Controladores.

### 2. Invocación del Handler — REGLA CRÍTICA
**ANTES** de planificar cómo el controlador llama al Handler, inspecciona el archivo PHP del Handler para determinar cómo está definido su método público principal.
* Si el Handler tiene `public function handle(...)`: el controlador debe llamarlo como `$handler->handle($input)`.
* Si el Handler tiene `public function __invoke(...)`: el controlador puede llamarlo como `($handler)($input)`.
* **NUNCA** asumas que todos los Handlers son invocables (`__invoke`). Verifica el método real en cada Handler antes de planificar su llamada. *(Este error causó un 500: Object is not callable en producción).*

### 3. Validación de Entrada (Form Requests)
* Toda petición que envíe datos (POST, PUT, PATCH) debe ser validada mediante clases `FormRequest` de Laravel.
* El `FormRequest` valida formato, longitud, tipos de datos y reglas básicas HTTP (`required`, `string`, `max:255`, `email`). Las reglas de negocio profundas pertenecen al Dominio.

### 4. Transformación de Salida (API Resources)
* Las respuestas deben estar estandarizadas usando `JsonResource` o `ResourceCollection` de Laravel.
* El recurso envolverá el Output/DTO que devuelve la capa de Aplicación.
* Verifica los campos reales del Output DTO — **nunca asumas su estructura**. Mapea exactamente las propiedades públicas que expone el Output.

### 5. Diseño de Rutas RESTful
* Define las rutas en el archivo `api.php` del módulo.
* Usa nombres de recursos estándar (ej. `/api/v1/solicitudes-prestamo`).
* Respeta la semántica de los verbos HTTP: `GET` para leer, `POST` para crear, `PUT/PATCH` para actualizar, `DELETE` para eliminar.
* Si el Caso de Uso no encaja en un CRUD tradicional (ej. "Aprobar Solicitud"), modela la ruta como sub-recurso (ej. `POST /api/v1/solicitudes/{id}/aprobaciones`).

### 6. Limpieza de Archivos Placeholder — REGLA CRÍTICA
El módulo puede tener un controlador placeholder genérico generado automáticamente por nwidart (ej. `GestionPrestamosRecepcionesController`). El plan debe incluir explícitamente:
* **Eliminar** el archivo del controlador placeholder.
* **Limpiar `routes/web.php`** del módulo: si el módulo es API-only, este archivo debe quedar vacío (solo el `<?php` y el `use Route`). Si `web.php` mantiene una referencia al controlador eliminado, Laravel lanzará un `ReflectionException` al listar o cargar rutas, bloqueando toda la aplicación.
* **Limpiar `routes/api.php`:** reemplazar cualquier `apiResource` placeholder por las rutas reales.
* **Ejecutar `composer dump-autoload`** después de eliminar cualquier archivo PHP, para que el autoloader de Composer no intente cargar clases eliminadas.

### 7. Manejo de Respuestas y Códigos HTTP
* `200 OK` para consultas exitosas o actualizaciones.
* `201 Created` tras crear un recurso exitosamente.
* `422 Unprocessable Entity` para errores de validación de `FormRequest` o excepciones de negocio del dominio.
* `404 Not Found` si el Handler arroja una excepción de "no encontrado".
* Planifica el mapeo de excepciones de dominio en `bootstrap/app.php` usando `withExceptions()`. Sin este mapeo, una excepción de dominio como `EntidadNoEncontradaException` se renderizará como 500 en lugar de 404.

---

## Entregable Esperado
Presenta un Plan de Diseño Estructurado que incluya:
1. **Auditoría previa obligatoria:** Qué archivos placeholder existen y cuáles deben eliminarse/limpiarse.
2. **Firma de cada Handler:** Qué método expone cada Handler (`handle` vs `__invoke`) y cómo debe llamarlo el controlador.
3. **Mapa de Rutas (Endpoints):** Listado de rutas REST con verbos HTTP y URIs.
4. **FormRequests a crear:** Con los campos exactos según los Input DTOs inspeccionados.
5. **API Resources a crear:** Con los campos exactos según los Output DTOs inspeccionados.
6. **Seudocódigo del Controlador:** Demostrando la llamada correcta al Handler (`->handle()` o invocable).
7. **Pasos de limpieza post-implementación:** `composer dump-autoload`, verificación con `php artisan route:list`.
