#language: es
Característica: Sincronización del estado del espécimen al ser devuelto
  Como sistema de gestión,
  quiero que el estado del espécimen se restaure a "disponible" al recibir el evento EspecimenDevuelto,
  para reflejar la disponibilidad real del espécimen en la colección.

  Escenario: Espécimen vuelve a "disponible" al recibir el evento EspecimenDevuelto
    Dado que existe un espécimen en estado prestado
    Cuando el sistema recibe el evento EspecimenDevuelto para ese espécimen
    Entonces el espécimen queda en estado disponible

  Escenario: No se actualiza si el espécimen ya está disponible
    Dado que existe un espécimen en estado disponible
    Cuando el sistema recibe el evento EspecimenDevuelto para ese espécimen
    Entonces el sistema rechaza la transición indicando que el espécimen ya está disponible
