# language: es
Característica: Registro y listado de localidades canónicas
  Como curador responsable de la colección,
  quiero registrar y listar localidades canónicas con su jerarquía,
  para enlazar especímenes y muestras a ubicaciones revisadas.

  Escenario: El curador registra una localidad raíz
    Dado que el curador tiene los datos de una nueva localidad
    Cuando el curador registra la localidad
    Entonces la localidad queda registrada en el sistema

  Escenario: El curador no puede registrar una localidad duplicada (mismo nombre y rango)
    Dado que existe una localidad registrada en el sistema
    Cuando el curador intenta registrar la misma localidad
    Entonces el sistema rechaza el registro indicando que ya existe

  Escenario: El curador no puede registrar una localidad con un rango inválido
    Dado que el curador tiene datos de una localidad con rango inválido
    Cuando el curador registra la localidad
    Entonces el sistema rechaza el registro indicando rango inválido

  Escenario: El curador lista las localidades registradas paginadas
    Dado que existen varias localidades registradas en el sistema
    Cuando el curador lista las localidades
    Entonces se muestran las localidades ordenadas por nombre con su jerarquía
