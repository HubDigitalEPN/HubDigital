# language: es
@listo
Característica: Trazabilidad de movimientos de especímenes, unit trays y cajas
  Como curador de la colección,
  quiero un registro trazable de cada movimiento de un espécimen y de los contenedores que lo albergan,
  para reconstruir su recorrido y saber quién realizó cada cambio.

  Esquema del escenario: El curador consulta la trazabilidad de un espécimen
    Dado que existe un espécimen <condicion>
    Cuando el curador consulta la trazabilidad del espécimen
    Entonces el historial es retornado <resultado>

    Ejemplos:
      | condicion               | resultado                                |
      | con movimientos previos | con los movimientos en orden cronológico |
      | sin movimientos         | vacío                                    |

  Esquema del escenario: El curador revisa quién movió cada elemento y entre qué ubicaciones
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

  Escenario: El curador ve en la trazabilidad de un espécimen los movimientos de sus contenedores
    Dado que existe un espécimen alojado en un unit tray dentro de una caja
    Y el unit tray y la caja que lo contienen registraron movimientos
    Cuando el curador consulta la trazabilidad del espécimen
    Entonces el historial incluye los movimientos del unit tray y de la caja que lo contienen
