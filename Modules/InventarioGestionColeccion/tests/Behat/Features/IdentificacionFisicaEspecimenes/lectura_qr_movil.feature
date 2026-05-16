#language: es
Característica: Resolución del QR a la ficha digital del espécimen
  Como curador o investigador en campo,
  quiero escanear el código QR de un espécimen y obtener su ficha digital,
  para verificar su información sin acceder manualmente al sistema.

  Escenario: Resolver QR válido a la ficha del espécimen
    Dado que existe un espécimen con su código QR generado
    Cuando se resuelve el QR con el payload correcto del espécimen
    Entonces se retorna la ficha digital completa del espécimen

  Escenario: QR con payload inválido retorna error
    Dado que se intenta resolver un QR con un payload que no corresponde a ningún espécimen
    Cuando se procesa el payload del QR inválido
    Entonces el sistema retorna un error indicando que el espécimen no fue encontrado
