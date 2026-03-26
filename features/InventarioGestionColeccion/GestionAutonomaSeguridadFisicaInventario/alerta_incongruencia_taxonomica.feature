# language: es
Característica: Alerta de incongruencia taxonómica por ubicación incorrecta
  Como Curador,
  quiero recibir una alerta inmediata si inserto una caja en una ranura que pertenece a otra familia taxonómica,
  para proteger el valor científico y el orden de la colección.

  Escenario: Inserción de una caja en una ranura con la familia taxonómica correcta
    Dado que el gabinete "GAB-01" está configurado para la familia "Carabidae"
    Y tengo una caja entomológica "CAJA-200" con especímenes de la familia "Carabidae"
    Cuando inserto la caja "CAJA-200" en una ranura vacía del gabinete "GAB-01"
    Entonces se debe registrar el ingreso exitoso de la caja

  Escenario: Inserción de una caja en una ranura con una familia taxonómica incorrecta
    Dado que el gabinete "GAB-01" está configurado para la familia "Carabidae"
    Y tengo una caja entomológica "CAJA-300" con especímenes de la familia "Nymphalidae"
    Cuando inserto la caja "CAJA-300" en una ranura vacía del gabinete "GAB-01"
    Entonces se debe generar una alerta de "Incongruencia Taxonómica"
    Y la alerta debe notificar inmediatamente al curador responsable
    Y el estado de la caja debe marcarse como "Ubicación Incorrecta"
