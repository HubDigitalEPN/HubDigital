# language: es
Característica: Reubicación digital guiada de especímenes
  Como Curador,
  quiero registrar el traslado de un espécimen vinculando su contenedor de origen con el de destino,
  para mantener un historial de custodia estricto y asegurar que el inventario digital refleje exactamente la realidad física.

  Escenario: Traslado exitoso de un espécimen de una caja a otra
    Dado que el espécimen "ESP-001" está ubicado en la caja de origen "CAJA-A"
    Y tengo una caja de destino "CAJA-B" con espacio disponible
    Cuando inicio el proceso de reubicación digital guiada para el espécimen "ESP-001"
    Y selecciono la caja "CAJA-B" como destino
    Entonces se debe actualizar la ubicación del espécimen a "CAJA-B"
    Y se debe guardar un registro en el historial de custodia con los contenedores de origen y destino
    Y el estado del espécimen debe reflejar "Reubicado"

  Escenario: Intento de traslado a un contenedor lleno
    Dado que el espécimen "ESP-002" está en la caja "CAJA-A"
    Y la caja de destino "CAJA-C" ha alcanzado su capacidad máxima
    Cuando intento reubicar el espécimen "ESP-002" en la caja "CAJA-C"
    Entonces se debe mostrar un mensaje de error "Contenedor destino lleno"
