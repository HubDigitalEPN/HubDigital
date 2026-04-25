erDiagram
  solicitudes_prestamo ||--o{ items_prestamo : contiene
  solicitudes_prestamo ||--o| actas_prestamo : genera
  actas_prestamo ||--o| prestamos : origina
  solicitudes_prestamo ||--o{ eventos_ciclo_prestamo : emite
  actas_prestamo ||--o{ eventos_ciclo_prestamo : emite
  prestamos ||--o{ eventos_ciclo_prestamo : emite

  solicitudes_prestamo {
    uuid id PK
    varchar numero_solicitud UK
    uuid investigador_id
    varchar estado "borrador enviada observada aprobada rechazada"
    varchar titulo_estudio
    varchar institucion_adscripcion
    varchar linea_investigacion
    text proposito_prestamo
    smallint duracion_propuesta_meses
    text justificacion_extendida "nullable"
    text comentario_curador "nullable"
    timestamptz enviada_en "nullable"
    timestamptz resuelta_en "nullable"
    uuid resuelta_por "nullable"
    timestamptz creada_en
    timestamptz actualizada_en
  }

  items_prestamo {
    uuid id PK
    uuid solicitud_prestamo_id FK
    varchar especimen_codigo_externo
    jsonb especimen_snapshot "incluye research_permit y taxonomia"
    smallint cantidad_solicitada
    text condiciones_especificas "nullable fijadas por curador al aprobar"
    timestamptz creado_en
    timestamptz actualizado_en
  }

  actas_prestamo {
    uuid id PK
    varchar numero_prestamo UK "ej. MEPN-INV-002-2026"
    uuid solicitud_prestamo_id FK "UNIQUE 1 a 1"
    varchar estado "pendiente_envio pendiente_firma pendiente_validacion validada"
    varchar tipo_prestamo "temporal o permanente"
    date fecha_inicio
    date fecha_fin
    text condiciones_adicionales "nullable"
    timestamptz emitida_en
    timestamptz enviada_en "nullable"
    varchar pdf_ruta
    varchar pdf_firmado_ruta "nullable"
    timestamptz firmada_subida_en "nullable"
    timestamptz validada_en "nullable"
    uuid validada_por "nullable"
    timestamptz creada_en
    timestamptz actualizada_en
  }

  prestamos {
    uuid id PK
    uuid acta_prestamo_id FK "UNIQUE 1 a 1"
    varchar estado "F1 activo  F2+ vencido cerrado etc"
    timestamptz iniciado_en
    timestamptz creado_en
    timestamptz actualizado_en
  }

  eventos_ciclo_prestamo {
    bigserial id PK
    varchar tipo_agregado "solicitud acta prestamo"
    uuid agregado_id "ref polimorfica"
    varchar tipo_evento
    smallint version_evento
    jsonb datos
    uuid actor_id
    varchar actor_rol "investigador curador sistema"
    timestamptz ocurrido_en
    timestamptz creado_en
  }
