erDiagram

  taxones ||--o{ taxones : "padre_de"
  taxones ||--o{ especimenes : "clasifica"
  taxones ||--o{ gabinetes : "configura"
  taxones ||--o{ cajas_entomologicas : "asignada_a"

  especimenes ||--o| codigos_qr : "tiene"
  especimenes ||--o{ historial_custodia : "registra"
  especimenes ||--o{ validaciones_darwin_core : "genera"
  especimenes }o--|| cajas_entomologicas : "ubicado_en"

  entidades_depositantes ||--o{ actas_entrega : "origina"
  actas_entrega ||--o{ actas_entrega_especimenes : "contiene"
  especimenes ||--o{ actas_entrega_especimenes : "incluido_en"

  gabinetes ||--o{ ranuras : "contiene"
  ranuras ||--o| cajas_entomologicas : "ocupa"
  cajas_entomologicas ||--o{ historial_custodia : "registra"
  cajas_entomologicas ||--o{ alertas_inventario : "genera"

  lotes_etl ||--o{ registros_etl : "contiene"

  taxones {
    uuid id PK
    uuid parent_id FK "nullable"
    varchar nombre_cientifico
    varchar rango "reino filo clase orden familia genero especie"
    varchar estado "activo inactivo"
    timestamptz creado_en
    timestamptz actualizado_en
  }

  especimenes {
    uuid guid PK
    varchar codigo_catalogo UK
    uuid taxon_id FK
    varchar localidad
    date fecha_colecta
    varchar colector
    varchar estado "disponible prestado en_transito extraviado"
    boolean darwin_core_valido
    uuid caja_id FK "nullable"
    timestamptz creado_en
    timestamptz actualizado_en
  }

  codigos_qr {
    uuid id PK
    uuid especimen_guid FK
    varchar payload UK
    varchar ruta_imagen
    timestamptz generado_en
  }

  entidades_depositantes {
    uuid id PK
    varchar nombre UK
    varchar tipo "persona institucion"
    varchar contacto "nullable"
    timestamptz creado_en
    timestamptz actualizado_en
  }

  actas_entrega {
    uuid id PK
    uuid entidad_depositante_id FK
    uuid curador_id
    varchar estado "borrador generada descargada"
    varchar pdf_ruta "nullable"
    timestamptz emitida_en "nullable"
    timestamptz creada_en
  }

  actas_entrega_especimenes {
    uuid acta_entrega_id FK
    uuid especimen_guid FK
  }

  gabinetes {
    uuid id PK
    varchar codigo UK
    uuid familia_taxon_id FK
    varchar ubicacion
    timestamptz creado_en
  }

  ranuras {
    uuid id PK
    uuid gabinete_id FK
    smallint numero
    varchar estado "libre ocupada"
  }

  cajas_entomologicas {
    uuid id PK
    varchar codigo UK
    uuid familia_taxon_id FK "nullable"
    uuid ranura_id FK "nullable"
    varchar estado "en_gabinete en_transito extraccion_prolongada ubicacion_incorrecta pendiente_clasificacion"
    timestamptz retirada_en "nullable"
  }

  historial_custodia {
    bigserial id PK
    uuid especimen_guid FK "nullable"
    uuid caja_id FK "nullable"
    varchar tipo_evento "ingreso retiro reubicacion"
    uuid caja_origen_id "nullable"
    uuid caja_destino_id "nullable"
    uuid actor_id
    timestamptz ocurrido_en
  }

  alertas_inventario {
    uuid id PK
    uuid caja_id FK "nullable"
    varchar tipo "orden_taxonomico_fuera_de_secuencia movimiento_no_autorizado extraccion_prolongada familia_no_asignada"
    varchar estado "activa resuelta"
    jsonb datos
    timestamptz generada_en
  }

  validaciones_darwin_core {
    uuid id PK
    uuid especimen_guid FK
    varchar campo
    varchar error "nullable"
    boolean paso
    timestamptz validado_en
  }

  lotes_etl {
    uuid id PK
    varchar nombre
    integer total_registros
    integer migrados
    integer rechazados
    varchar estado "pendiente procesando completado"
    timestamptz creado_en
    timestamptz completado_en "nullable"
  }

  registros_etl {
    uuid id PK
    uuid lote_id FK
    jsonb datos_originales
    varchar estado "migrado rechazado"
    varchar causa_error "nullable"
    uuid especimen_guid FK "nullable"
    timestamptz procesado_en
  }
