# language: es
@listo
Característica: El régimen de custodia decide qué material puede prestarse
  Como curador,
  quiero que la colección no ofrezca material que ya no custodia,
  para no comprometer en un préstamo especímenes que salieron o están aislados.

  Escenario: El curador no encuentra material devuelto a su depositante
    Dado que el catálogo tiene un espécimen devuelto a su depositante
    Cuando el curador busca especímenes prestables
    Entonces la colección no ofrece ese espécimen

  Escenario: El curador no encuentra material en cuarentena
    Dado que el catálogo tiene un espécimen en cuarentena
    Cuando el curador busca especímenes prestables
    Entonces la colección no ofrece ese espécimen

  Escenario: El curador sí encuentra material en depósito temporal
    Dado que el catálogo tiene un espécimen en depósito temporal
    Cuando el curador busca especímenes prestables
    Entonces la colección ofrece ese espécimen

  Escenario: El curador sí encuentra material del catálogo sin régimen declarado
    Dado que el catálogo tiene un espécimen heredado sin régimen de custodia
    Cuando el curador busca especímenes prestables
    Entonces la colección ofrece ese espécimen

  Escenario: El sistema impide comprometer en un préstamo material devuelto
    Dado que el catálogo tiene un espécimen devuelto a su depositante
    Cuando el sistema intenta comprometer ese espécimen en un préstamo
    Entonces el sistema rechaza comprometerlo porque ya no está en la colección
