# language: es
# Pendiente de implementar: el Context de esta feature aún no está registrado en behat.php.
@pendiente
Característica: Trazabilidad de movimientos de especímenes, unit trays y cajas
  Como curador de la colección,
  quiero un registro trazable de cada movimiento de un espécimen y de los contenedores que lo albergan,
  para reconstruir su recorrido y saber quién realizó cada cambio.

  Esquema del escenario: El curador consulta la trazabilidad de un espécimen
    Dado que existe un espécimen con <condicion>
    Cuando el curador consulta la trazabilidad del espécimen
    Entonces el historial es retornado <resultado>

    Ejemplos:
      | condicion           | resultado                                |
      | movimientos previos | con los movimientos en orden cronológico |
      | sin movimientos     | vacío                                    |

  Esquema del escenario: Cada reubicación queda registrada indicando qué se movió y quién lo hizo
    Dado que un <actor> reubicó <que>
    Cuando el curador consulta la trazabilidad del elemento reubicado
    Entonces el registro del movimiento indica el origen, el destino y el momento en que ocurrió
    Y el registro identifica al <actor> como responsable

    Ejemplos:
      | actor                | que                           |
      | curador              | un espécimen entre unit trays |
      | curador              | un unit tray entre cajas      |
      | visitante habilitado | un espécimen entre unit trays |

  Escenario: El sistema registra el movimiento de una caja sin un actor humano
    Dado que existe una caja ubicada en una ranura de un gabinete
    Cuando la caja se mueve a otra ranura
    Entonces el movimiento de la caja queda registrado en la trazabilidad sin un actor humano

  Escenario: La trazabilidad de un espécimen incluye los movimientos de los contenedores que lo albergan
    Dado que existe un espécimen asignado a un unit tray de una caja
    Y el unit tray o la caja que lo contienen registraron movimientos
    Cuando el curador consulta la trazabilidad del espécimen
    Entonces el historial incluye tanto las reubicaciones del espécimen como los movimientos del unit tray y de la caja que lo contienen
