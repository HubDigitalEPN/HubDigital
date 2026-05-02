#language: es
Característica: Gestión centralizada de entidades depositantes
  Como curador responsable de la colección,
  quiero registrar y gestionar personas e instituciones depositantes,
  para vincularlas correctamente a los especímenes recibidos.

  Escenario: Registrar una nueva entidad depositante
    Dado que el curador tiene los datos de una nueva entidad depositante
    Cuando el curador registra la entidad depositante
    Entonces la entidad depositante queda registrada en el sistema

  Escenario: Editar datos de una entidad depositante existente
    Dado que existe una entidad depositante registrada en el sistema
    Cuando el curador actualiza los datos de la entidad depositante
    Entonces la entidad depositante refleja la información actualizada

  Escenario: No se puede registrar una entidad depositante con nombre duplicado
    Dado que existe una entidad depositante con el nombre "Universidad Central del Ecuador"
    Cuando el curador intenta registrar otra entidad con el mismo nombre
    Entonces el sistema rechaza el registro indicando nombre de entidad duplicado
