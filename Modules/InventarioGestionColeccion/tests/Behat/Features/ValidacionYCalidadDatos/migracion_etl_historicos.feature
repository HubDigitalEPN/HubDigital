#language: es
Característica: Carga y normalización de datos históricos mediante ETL
  Como administrador del sistema,
  quiero cargar datos históricos de especímenes mediante un proceso ETL,
  para normalizar la información sin pérdida de datos.

  Escenario: Migrar registros históricos válidos exitosamente
    Dado que existe un lote de registros históricos con datos válidos
    Cuando se ejecuta el proceso ETL sobre el lote
    Entonces los registros son migrados y quedan en estado migrado

  Esquema del escenario: Registros con campo inválido son rechazados durante el ETL
    Dado que existe un lote con un registro que tiene el campo "<campo>" inválido
    Cuando se ejecuta el proceso ETL sobre el lote
    Entonces el registro es rechazado con causa de error que menciona "<campo>"

    Ejemplos:
      | campo             |
      | nombre_cientifico |
      | fecha_colecta     |
      | localidad         |
