# language: es
# Pendiente de implementar: el Context de esta feature aún no está registrado en behat.php.
@pendiente
Característica: Reubicación digital guiada de especímenes por escaneo de QR
  Como curador de la colección,
  quiero reasignar especímenes a otro unit tray escaneando el código QR del unit tray de destino y el de cada espécimen,
  para actualizar el inventario digital desde el laboratorio sin capturar datos a mano y conservando el orden taxonómico.

  Escenario: El curador reasigna un espécimen a otro unit tray escaneando los códigos QR
    Dado que existe un espécimen asignado a un unit tray de origen
    Y existe un unit tray de destino donde el espécimen respeta el orden alfabético por especie
    Cuando el curador escanea el código QR del unit tray de destino y el del espécimen
    Entonces el espécimen queda asignado al unit tray de destino
    Y se guarda un registro en el historial de custodia con los unit trays de origen y destino

  Escenario: El curador reasigna varios especímenes a un unit tray en una sola operación
    Dado que existen varios especímenes asignados a unit trays de origen
    Y existe un unit tray de destino con espacio disponible
    Cuando el curador escanea el código QR del unit tray de destino y el de cada espécimen
    Entonces todos los especímenes quedan asignados al unit tray de destino
    Y se guarda un registro en el historial de custodia por cada espécimen reubicado

  Escenario: El curador recibe una advertencia al reasignar un espécimen que no pertenece al unit tray de destino
    Dado que existe un espécimen asignado a un unit tray de origen
    Y en el unit tray de destino el espécimen quedaría fuera del orden alfabético por especie
    Cuando el curador escanea el código QR del unit tray de destino y el del espécimen
    Entonces se advierte que el espécimen no pertenece taxonómicamente al unit tray de destino
    Y el curador puede confirmar la reubicación o cancelarla

  Esquema del escenario: El curador consulta el historial de custodia de un espécimen
    Dado que existe un espécimen con <condicion>
    Cuando el curador solicita el historial de custodia del espécimen
    Entonces el historial es retornado <resultado>

    Ejemplos:
      | condicion             | resultado                                |
      | reubicaciones previas | con los movimientos en orden cronológico |
      | sin reubicaciones     | vacío                                    |
