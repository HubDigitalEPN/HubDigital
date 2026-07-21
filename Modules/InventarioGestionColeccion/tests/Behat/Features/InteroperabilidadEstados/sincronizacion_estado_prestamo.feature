# language: es
Característica: Sincronización del catálogo cuando un préstamo se activa
  Como sistema de gestión,
  quiero sacar de circulación los especímenes de un préstamo recién activado,
  para que no puedan solicitarse en otro préstamo mientras están fuera.

  Escenario: El sistema saca de circulación un espécimen disponible
    Dado que el catálogo tiene un espécimen disponible
    Cuando el sistema sincroniza la salida en préstamo de ese espécimen
    Entonces el catálogo deja el espécimen en préstamo

  Escenario: El sistema reporta un espécimen que ya estaba prestado sin interrumpir el lote
    Dado que el catálogo tiene un espécimen ya prestado
    Cuando el sistema sincroniza la salida en préstamo de ese espécimen
    Entonces el sistema reporta el espécimen como ya prestado

  Escenario: El sistema reporta un identificador sin espécimen en el catálogo
    Dado que el identificador a prestar no corresponde a ningún espécimen
    Cuando el sistema sincroniza la salida en préstamo de ese espécimen
    Entonces el sistema reporta el espécimen como no encontrado al prestar
