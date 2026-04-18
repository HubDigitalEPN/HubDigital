#language: es
Característica: Reubicación digital guiada de especímenes
  Como curador responsable de la colección,
  quiero registrar el traslado de un espécimen vinculando su contenedor de origen con el de destino,
  para mantener un historial de custodia estricto y asegurar que el inventario digital refleje exactamente la realidad física.

  Escenario: Traslado exitoso de un espécimen de una caja a otra
    Dado que existe un espécimen ubicado en una caja de origen
    Y existe una caja de destino con espacio disponible y familia taxonómica compatible
    Cuando inicio el proceso de reubicación digital guiada para el espécimen
    Y selecciono la caja de destino
    Entonces se debe actualizar la ubicación del espécimen a la caja de destino
    Y se debe guardar un registro en el historial de custodia con los contenedores de origen y destino
    Y el estado del espécimen debe reflejar "Reubicado"

  Escenario: Intento de traslado a un contenedor lleno
    Dado que existe un espécimen ubicado en una caja de origen
    Y la caja de destino ha alcanzado su capacidad máxima
    Cuando intento reubicar el espécimen en la caja de destino
    Entonces se debe mostrar un mensaje de error "Contenedor destino lleno"
    Y el espécimen permanece en su contenedor de origen

  Escenario: Intento de traslado a contenedor con familia taxonómica incompatible
    Dado que existe un espécimen de una familia taxonómica específica en una caja de origen
    Y la caja de destino está configurada para una familia taxonómica diferente
    Cuando intento reubicar el espécimen en la caja de destino
    Entonces se debe mostrar un mensaje de error "Incompatibilidad Taxonómica"
    Y el espécimen permanece en su contenedor de origen

  Esquema del escenario: Consultar el historial de custodia de un espécimen
    Dado que existe un espécimen con <condicion>
    Cuando el curador solicita el historial de custodia del espécimen
    Entonces el historial es retornado <resultado>

    Ejemplos:
      | condicion             | resultado                                  |
      | reubicaciones previas | con los movimientos en orden cronológico   |
      | sin reubicaciones     | vacío                                      |
