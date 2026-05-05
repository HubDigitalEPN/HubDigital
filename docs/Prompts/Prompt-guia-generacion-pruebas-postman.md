# Prompt: Generación de Guía de Pruebas Postman (API REST)

**Contexto:**
La Capa de Presentación (Controladores, FormRequests, API Resources y Rutas) del módulo actual ya ha sido implementada y validada. Ahora necesito probar todos los endpoints creados utilizando Postman para verificar que el flujo completo (desde el Request hasta la Base de Datos) funcione correctamente en mi entorno local.

Mi entorno de desarrollo local utiliza **Laravel Herd**, por lo que la URL base de mi aplicación es `https://[nombre-de-mi-app].test/api/v1` (reemplaza `[nombre-de-mi-app]` por el nombre real del proyecto si lo conoces, o usa un placeholder evidente).

---

**Tu Tarea:**
Actúa como un QA Engineer especializado en APIs. Debes generar un documento estructurado en formato Markdown que sirva como un manual paso a paso para probar **cada uno de los endpoints** recién creados en Postman.

---

**Directrices Estrictas para la Guía:**

1. **Ajuste Temporal de Rutas (Desbloqueo de Auth):**
   Dado que en esta fase de desarrollo es probable que el módulo de autenticación de usuarios aún no esté listo, incluye una nota clara al principio del documento indicando que se debe comentar o eliminar temporalmente el middleware `auth:sanctum` de `routes/api.php` para evitar errores `401 Unauthorized`.

2. **Estructura Obligatoria por Endpoint:**
   Para CADA endpoint desarrollado en este módulo, debes generar una sección que contenga estrictamente lo siguiente:
    * **Endpoint:** Verbo HTTP y la URL completa usando el dominio de Herd.
    * **Headers:** Tabla con los headers obligatorios (ej. `Accept: application/json`).
    * **Body (Payload):**
        * Si es una petición JSON (raw), proporciona un bloque de código con un JSON válido y realista que cumpla al 100% con las reglas definidas en su `FormRequest` correspondiente.
        * Si el endpoint maneja subida de archivos (multipart/form-data), explica paso a paso cómo configurar la pestaña `Body -> form-data` en Postman, indicando qué campos son `Text` y cuáles son `File`.
    * **Respuesta Esperada:** El código de estado HTTP de éxito esperado (ej. `201 Created` o `200 OK`) y un bloque de código JSON mostrando exactamente la estructura de respuesta que generará el `API Resource` asociado.
    * **Notas de Trazabilidad:** Si el endpoint devuelve un ID (ej. al crear un recurso) que se necesita en los siguientes pasos, añade un `> blockquote` recordando al usuario que copie ese valor.

3. **Resumen de Códigos HTTP:**
   Al final del documento, incluye una tabla resumen con todos los códigos HTTP de éxito y error (`422`, `404`, etc.) que el usuario podría encontrarse al probar este módulo específico.

**Entregable:**
Genera únicamente la guía en formato Markdown lista para ser leída y ejecutada.
