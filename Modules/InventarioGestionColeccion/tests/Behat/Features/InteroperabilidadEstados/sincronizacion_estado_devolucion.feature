# language: es
Característica: Sincronización del catálogo cuando un préstamo se cierra
  Como sistema de gestión,
  quiero devolver al catálogo cada espécimen según cómo lo verificó el curador,
  para que la disponibilidad refleje el estado real de la colección.

  Escenario: El sistema devuelve a circulación un espécimen sin novedades
    Dado que el catálogo tiene un espécimen en préstamo por cerrar
    Cuando el sistema restaura ese espécimen sin novedades
    Entonces el catálogo deja el espécimen disponible

  Escenario: El sistema deja observado un espécimen que volvió con novedad
    Dado que el catálogo tiene un espécimen en préstamo por cerrar
    Cuando el sistema restaura ese espécimen con novedad
    Entonces el catálogo deja el espécimen observado

  Escenario: El sistema restaura un espécimen ya disponible sin interrumpir el lote
    Dado que el catálogo tiene un espécimen ya disponible por cerrar
    Cuando el sistema restaura ese espécimen sin novedades
    Entonces el catálogo deja el espécimen disponible

  Escenario: El sistema reporta un identificador sin espécimen en el catálogo
    Dado que el identificador a restaurar no corresponde a ningún espécimen
    Cuando el sistema restaura ese espécimen sin novedades
    Entonces el sistema reporta el espécimen como no encontrado al restaurar
