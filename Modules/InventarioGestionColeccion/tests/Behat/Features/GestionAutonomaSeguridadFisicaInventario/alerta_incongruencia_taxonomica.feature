#language: es
Característica: Alerta de incongruencia taxonómica por ubicación incorrecta
  Como curador responsable de la colección,
  quiero recibir una alerta inmediata si inserto una caja en una ranura que pertenece a otra familia taxonómica,
  para proteger el valor científico y el orden de la colección.

  Esquema del escenario: Inserción de una caja en un gabinete según coincidencia taxonómica
    Dado que existe un gabinete configurado para una familia taxonómica
    Y existe una caja entomológica con especímenes de familia <coincidencia>
    Cuando inserto la caja en una ranura vacía del gabinete
    Entonces <resultado>

    Ejemplos:
      | coincidencia | resultado                                                                                                     |
      | correcta     | se debe registrar el ingreso exitoso de la caja                                                               |
      | incorrecta   | se debe generar una alerta de "Incongruencia Taxonómica" y el estado de la caja se marca como "Ubicación Incorrecta" |

  Escenario: Inserción de una caja sin familia taxonómica asignada
    Dado que existe un gabinete configurado para una familia taxonómica
    Y existe una caja entomológica sin familia taxonómica asignada
    Cuando inserto la caja en una ranura vacía del gabinete
    Entonces se debe generar una alerta de "Familia No Asignada"
    Y el estado de la caja debe marcarse como "Pendiente de Clasificación"
