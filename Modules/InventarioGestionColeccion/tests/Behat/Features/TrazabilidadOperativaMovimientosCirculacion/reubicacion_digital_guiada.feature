# language: es
@listo
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

  Escenario: El unit tray de origen queda sin clasificación taxonómica al vaciarse por una reubicación
    Dado que existe un espécimen asignado a un unit tray de origen
    Y existe un unit tray de destino donde el espécimen respeta el orden taxonómico
    Cuando el curador reubica el espécimen al unit tray de destino
    Entonces el espécimen queda asignado al unit tray de destino
    Y el unit tray de origen queda sin clasificación taxonómica

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

  Escenario: El curador recibe una advertencia y cancela al reubicar un espécimen que no pertenece al unit tray de destino
    Dado que existe un espécimen asignado a un unit tray de origen
    Y en el unit tray de destino el espécimen quedaría fuera del orden taxonómico
    Cuando el curador reubica el espécimen al unit tray de destino
    Entonces se advierte que el espécimen no pertenece taxonómicamente al unit tray de destino
    Y si el curador cancela, el espécimen permanece en su unit tray de origen

  Escenario: El curador confirma la reubicación de un espécimen pese a la advertencia taxonómica
    Dado que existe un espécimen asignado a un unit tray de origen
    Y en el unit tray de destino el espécimen quedaría fuera del orden taxonómico
    Cuando el curador confirma la reubicación del espécimen al unit tray de destino pese a la advertencia
    Entonces el espécimen queda asignado al unit tray de destino
    Y el movimiento queda registrado en la trazabilidad del espécimen

  Escenario: El curador reubica un espécimen a una caja especial y el movimiento queda registrado
    Dado que existe un espécimen asignado a un unit tray de origen
    Y existe un unit tray de destino en una caja especial donde el espécimen quedaría fuera del orden taxonómico
    Cuando el curador reubica el espécimen al unit tray de destino
    Entonces el espécimen queda asignado al unit tray de destino
    Y el movimiento queda registrado en la trazabilidad del espécimen

  Escenario: El visitante habilitado para reubicar reasigna un espécimen a otro unit tray
    Dado que existe un visitante habilitado para reubicar
    Y existe un espécimen asignado a un unit tray de origen
    Y existe un unit tray de destino donde el espécimen respeta el orden taxonómico
    Cuando el visitante reubica el espécimen al unit tray de destino
    Entonces el espécimen queda asignado al unit tray de destino
    Y el movimiento queda registrado en la trazabilidad identificando al visitante como responsable

  Escenario: El visitante sin la habilitación no puede reubicar
    Dado que existe un visitante sin la habilitación para reubicar
    Y existe un espécimen asignado a un unit tray de origen
    Cuando el visitante intenta reubicar el espécimen a otro unit tray
    Entonces la reubicación es rechazada
    Y el espécimen permanece en su unit tray de origen
