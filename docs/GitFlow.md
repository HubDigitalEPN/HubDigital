# Guía de Commits Convencionales

Este estándar se aplicará a todos los repositorios de la **Plataforma Digital de Gestión de Colecciones de Biodiversidad**. El objetivo es mantener un historial de cambios legible, facilitar la generación de registros de cambios (changelogs) y mejorar la trazabilidad del proceso de ingeniería.

## 1. Estructura del Mensaje

Cada mensaje de commit debe seguir este formato:

```text
<tipo>(<alcance>): "<descripción corta en imperativo>"

[cuerpo del mensaje opcional]

[pie de página opcional]
```

## 2. Tipos de Commit (`type`)

Debes usar uno de los siguientes identificadores para el tipo de cambio:

* **`feat`**: Una nueva característica para el usuario o sistema.
* **`fix`**: Corrección de un error o bug.
* **`docs`**: Cambios solo en la documentación (ej. el README o este manual).
* **`style`**: Cambios que no afectan el significado del código (espacios, formato, puntos y coma faltantes).
* **`refactor`**: Un cambio en el código que ni corrige un error ni añade una funcionalidad (mejora de estructura).
* **`test`**: Añadir pruebas faltantes o corregir pruebas existentes.
* **`chore`**: Actualización de tareas de construcción, configuraciones de herramientas o librerías (ej. actualizar el `package.json` o `.gitignore`).

## 3. Alcances Específicos del Proyecto (`scope`)
Para mantener la coherencia con los componentes de la tesis, se definen los siguientes alcances obligatorios:

* **`taxonomia`**: Relacionado con la gestión de información taxonómica y migración de datos.
* **`prestamos`**: Relacionado con el motor de estados y formularios de préstamos.
* **`divulgacion`**: Relacionado con el portal público o el chatbot RAG.
* **`iot`**: Relacionado con los sensores, microcontroladores ESP32 y seguimiento físico.
* **`recepcion`**: Relacionado con el flujo de ingreso y validación de nuevos especímenes.
* **`core`**: Para cambios que afecten a la infraestructura compartida o base de datos global.
* **`deps`**: Para actualizaciones de dependencias o librerías externas.

## 4. Reglas de la Descripción (`subject`)

1.  Usa el imperativo, en tiempo presente: "agrega" en lugar de "agregado" o "agregando".
2.  No pongas punto final al terminar la descripción.
3.  La descripción debe ser breve (máximo 50-72 caracteres).

## 5. Ejemplos Prácticos de la Tesis

* **Nueva funcionalidad en IoT:**
    `feat(iot): agrega lógica de multiplexación para sensores RC522`
* **Corrección en el Chatbot:**
    `fix(divulgacion): corrige latencia en la respuesta del motor RAG`
* **Documentación de base de datos:**
    `docs(taxonomia): actualiza el diagrama de entidad-relación del herbario`
* **Refactorización de estados:**
    `refactor(prestamos): optimiza el switch de transiciones de estado`
* **Actualización de configuración:**
    `chore(core): configura variables de entorno para el servidor MQTT`

## 6. Breaking Changes (Cambios Importantes)

Si un cambio rompe la compatibilidad con versiones anteriores o cambia drásticamente la arquitectura (ej. cambio en el esquema de la base de datos), se debe añadir una `!` después del tipo y una sección `BREAKING CHANGE:` en el pie de página.

**Ejemplo:**
```text
feat(taxonomia)!: cambia el ID incremental por UUID para interoperabilidad DwC

BREAKING CHANGE: Las tablas de migración legacy ya no son compatibles con el esquema de IDs enteros.
```
## 7. Nombramiento de Ramas

Para mantener la coherencia con los commits, el nombrado de las ramas temporales debe seguir estrictamente el siguiente formato:

```text
<tipo>/<alcance>/<nombre-descriptivo>
```

- tipo: El tipo de trabajo que se está realizando (feature, fix, docs, refactor, etc.).

- scope: El módulo o componente afectado (ver sección 4).

- nombre-descriptivo: Una descripción corta, en minúsculas y separada por guiones (kebab-case).