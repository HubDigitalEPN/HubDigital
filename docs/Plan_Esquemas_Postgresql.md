# Plan de Organización de Base de Datos — Plataforma de Gestión de Colecciones de Biodiversidad

**Versión:** 1.0  
**Tecnología:** PostgreSQL 16  
**Alcance:** Definición estratégica de esquemas.

---

## 1. Principio Rector

La unidad de organización adoptada es el **esquema PostgreSQL**, no la base de datos ni el contenedor de despliegue.

El PostgreSQL Wiki establece explícitamente que "la recomendación es crear una única base de datos con múltiples esquemas nombrados", diferenciándose de la práctica más antigua de crear múltiples bases de datos.[^1] La ventaja determinante para este proyecto es que "el acceso cross-schema es posible desde una única conexión de base de datos",[^1] lo cual habilita transacciones ACID entre módulos sin infraestructura adicional.

Al tratarse de un **monolito modular**, todos los módulos comparten el mismo proceso Laravel y la misma conexión de base de datos. Bajo esta arquitectura, las FKs declaradas entre esquemas son técnicamente viables y deseables: el motor garantiza integridad referencial sin costo adicional de implementación, y no existe el riesgo de acoplamiento entre servicios independientes que justificaría omitirlas.

El criterio de corte para definir cada esquema es el **dominio de responsabilidad** (quién es dueño de los datos), no el contenedor de despliegue. Un contenedor es una decisión de infraestructura; un esquema es una decisión de datos. Son ejes ortogonales.

---

## 2. Topología

Una instancia PostgreSQL. Una base de datos. Siete esquemas nombrados.

```
biodiversidad_db  (una sola base de datos)
├── taxonomia
├── prestamos
├── divulgacion
├── divulgacion_vectorial
├── iot
├── recepcion
└── usuarios
```

El esquema `public` se mantiene vacío o se elimina.

---

## 3. Esquemas

- **`taxonomia`**: Relacionado con la gestión de información taxonómica y migración de datos. Es el esquema maestro de referencia; los demás esquemas pueden declarar FKs hacia él.
- **`prestamos`**: Relacionado con el motor de estados y formularios de préstamos.
- **`divulgacion`**: Relacionado con el portal público. Contiene proyecciones de datos de `taxonomia` orientadas a lectura pública; se sincroniza desde `taxonomia` mediante procesos batch.
- **`divulgacion_vectorial`**: Relacionado con el chatbot RAG del portal público. Se separa de `divulgacion` por naturaleza operacional distinta: índices HNSW en lugar de B-tree, escrituras batch periódicas de embeddings. Requiere la extensión `pgvector`.
- **`iot`**: Relacionado con los sensores, microcontroladores ESP32 y seguimiento físico.
- **`recepcion`**: Relacionado con el flujo de ingreso y validación de nuevos especímenes.
- **`usuarios`**: Relacionado con la gestión de usuarios registrados, incluyendo investigadores, instituciones y roles asociados. Esquema compartido para autenticación y autorización en módulos como préstamos y recepción.

---

## 4. Política de Relaciones Inter-Esquema

En un monolito modular, las FKs declaradas (`REFERENCES`) entre esquemas son válidas y recomendadas para todo lo que referencie entidades de `taxonomia` o `usuarios`, dado que son esquemas maestros y su estabilidad estructural lo permite.

Las FKs declaradas entre esquemas no-maestros (por ejemplo, `iot` → `prestamos`) deben evaluarse caso a caso: si el acoplamiento entre esos módulos es estable y necesario, se declaran; si es eventual o puede resolverse en la capa de consulta, se omite.

Las FKs declaradas (`REFERENCES`) **siempre** existen dentro del mismo esquema.

---

## 5. Control de Acceso por Esquema

Cada esquema tiene un rol propietario sin permiso de login y roles de aplicación con privilegios mínimos, siguiendo las recomendaciones del PostgreSQL Wiki.[^1]

En el monolito modular, el rol de aplicación principal (`app_user`) accede a todos los esquemas. Los roles de solo lectura se reservan para procesos externos específicos: el servidor del catálogo público y el proceso de indexación RAG.

| Esquema | `app_user` (monolito) | `public_reader` (catálogo) | `rag_indexer` (batch) |
|---|---|---|---|
| `taxonomia` | `SELECT, INSERT, UPDATE, DELETE` | — | — |
| `prestamos` | `SELECT, INSERT, UPDATE, DELETE` | — | — |
| `divulgacion` | `SELECT, INSERT, UPDATE` | `SELECT` | — |
| `divulgacion_rag` | `SELECT` | — | `SELECT, INSERT, UPDATE, DELETE` |
| `iot` | `SELECT, INSERT, UPDATE, DELETE` | — | — |
| `recepcion` | `SELECT, INSERT, UPDATE, DELETE` | — | — |
| `usuarios` | `SELECT, INSERT, UPDATE, DELETE` | — | — |

---

## 6. Prerrequisitos de Instalación

```sql
-- Habilitar pgvector una vez en la base de datos
CREATE EXTENSION IF NOT EXISTS vector;

-- Crear los esquemas
CREATE SCHEMA taxonomia;
CREATE SCHEMA prestamos;
CREATE SCHEMA divulgacion;
CREATE SCHEMA divulgacion_rag;
CREATE SCHEMA iot;
CREATE SCHEMA recepcion;
CREATE SCHEMA usuarios;

-- Eliminar privilegios del esquema public (recomendado)
-- REVOKE CREATE ON SCHEMA public FROM PUBLIC;
```

---

## 7. Resumen de Decisiones

| Decisión | Elección | Justificación |
|---|---|---|
| Topología | 1 instancia, 1 BD, 7 esquemas | Cross-schema en una conexión; administración unificada.[^1] |
| Granularidad del esquema | Por módulo/dominio, no por contenedor | El contenedor es infraestructura; el esquema es propiedad de datos. |
| FKs inter-esquema | Declaradas en motor | Monolito modular: un proceso, una conexión, sin riesgo de acoplamiento entre servicios.[^1] |
| Separación `divulgacion` / `divulgacion_rag` | Dos esquemas distintos | Naturaleza operacional diferente; índices y permisos independientes. |
| `public` | Vacío o eliminado | Recomendación explícita del PostgreSQL Wiki.[^1] |
| Esquema `usuarios` | Compartido para autenticación | Centraliza gestión de usuarios e instituciones para módulos como préstamos y recepción. |

---

[^1]: PostgreSQL Wiki, "Database Schema Recommendations for an Application," 2016. Disponible en: https://wiki.postgresql.org/wiki/Database_Schema_Recommendations_for_an_Application
