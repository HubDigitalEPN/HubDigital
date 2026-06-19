# language: es
@listo
Característica: Localización de cajas y alertas por movimiento no autorizado
  Como curador de la colección,
  quiero saber en qué ranura se encuentra cada caja
  y recibir una alerta cuando una caja se mueve fuera del horario autorizado,
  para mantener la integridad y seguridad de la colección.

  Escenario: El curador ubica una caja recién ingresada a una ranura
    Dado que la caja "B-042" está disponible fuera de un gabinete
    Y que la ranura 3 del gabinete "G-01" está libre
    Cuando la caja "B-042" es colocada en la ranura 3 del gabinete "G-01"
    Entonces el curador puede ver que "B-042" se encuentra en la ranura 3 del gabinete "G-01"

  Escenario: El curador sabe que una caja fue retirada de su ranura
    Dado que la caja "B-042" se encuentra en la ranura 3 del gabinete "G-01"
    Cuando la caja "B-042" es retirada de su ranura
    Entonces el curador puede ver que "B-042" no está ubicada en ninguna ranura

  Escenario: El curador retira una caja que estaba pendiente de clasificación
    Dado que la caja "B-042" se encuentra pendiente de clasificación en la ranura 3 del gabinete "G-01"
    Cuando la caja "B-042" es retirada de su ranura
    Entonces el curador puede ver que "B-042" no está ubicada en ninguna ranura

  Escenario: El curador recibe una alerta cuando una caja se mueve fuera del horario autorizado
    Dado que la caja "B-042" se encuentra en la ranura 3 del gabinete "G-01"
    Y que el movimiento ocurre fuera del horario autorizado de la colección
    Cuando la caja "B-042" es retirada de su ranura
    Entonces el curador recibe una alerta de movimiento no autorizado
