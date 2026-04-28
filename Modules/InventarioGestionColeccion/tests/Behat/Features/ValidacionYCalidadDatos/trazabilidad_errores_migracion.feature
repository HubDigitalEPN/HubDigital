#language: es
Característica: Reporte de registros rechazados durante el ETL
  Como administrador del sistema,
  quiero obtener un reporte de los registros rechazados durante la migración ETL,
  para corregirlos y volver a procesarlos.

  Escenario: Obtener reporte de registros rechazados en una migración
    Dado que existe un lote ETL con registros en estado rechazado
    Cuando se solicita el reporte de errores del lote
    Entonces el reporte incluye los registros rechazados con su causa de error

  Escenario: Un lote sin errores retorna reporte vacío
    Dado que existe un lote ETL con todos los registros en estado migrado
    Cuando se solicita el reporte de errores del lote
    Entonces el reporte retorna una lista vacía sin error
