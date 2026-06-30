# language: es
# Pendiente de implementar: el Context de esta feature aún no está registrado en behat.php.
@pendiente
Característica: Reubicación de especímenes y unit trays en la colección
  Como curador de la colección,
  quiero reasignar especímenes a otro unit tray y unit trays a otra caja,
  para mantener el inventario digital actualizado conservando el orden taxonómico.

  Escenario: El curador reubica un espécimen a otro unit tray
    Dado que existe un espécimen asignado a un unit tray de origen
    Y existe un unit tray de destino donde el espécimen respeta el orden taxonómico
    Cuando el curador reubica el espécimen al unit tray de destino
    Entonces el espécimen queda asignado al unit tray de destino
    Y el movimiento queda registrado en la trazabilidad del espécimen

  Escenario: El curador reubica varios especímenes a un unit tray en una sola operación
    Dado que existen varios especímenes asignados a unit trays de origen
    Y existe un unit tray de destino con espacio disponible
    Cuando el curador reubica los especímenes al unit tray de destino
    Entonces todos los especímenes quedan asignados al unit tray de destino
    Y se registra un movimiento en la trazabilidad por cada espécimen reubicado

  Escenario: El curador reubica un unit tray a otra caja
    Dado que existe un unit tray asignado a una caja de origen
    Y existe una caja de destino con espacio disponible
    Cuando el curador reubica el unit tray a la caja de destino
    Entonces el unit tray queda asignado a la caja de destino
    Y el movimiento queda registrado en la trazabilidad del unit tray

  Escenario: El curador recibe una advertencia al reubicar un espécimen que no pertenece al unit tray de destino
    Dado que existe un espécimen asignado a un unit tray de origen
    Y en el unit tray de destino el espécimen quedaría fuera del orden taxonómico
    Cuando el curador reubica el espécimen al unit tray de destino
    Entonces se advierte que el espécimen no pertenece taxonómicamente al unit tray de destino
    Y el curador puede confirmar la reubicación o cancelarla

  Escenario: El visitante habilitado para reubicar reasigna un espécimen a otro unit tray
    Dado que existe un visitante habilitado para reubicar
    Y existe un espécimen asignado a un unit tray de origen
    Cuando el visitante reubica el espécimen al unit tray de destino
    Entonces el espécimen queda asignado al unit tray de destino
    Y el movimiento queda registrado en la trazabilidad identificando al visitante como responsable

  Escenario: El visitante sin la habilitación no puede reubicar
    Dado que existe un visitante sin la habilitación para reubicar
    Y existe un espécimen asignado a un unit tray de origen
    Cuando el visitante intenta reubicar el espécimen a otro unit tray
    Entonces la reubicación es rechazada
    Y el espécimen permanece en su unit tray de origen
