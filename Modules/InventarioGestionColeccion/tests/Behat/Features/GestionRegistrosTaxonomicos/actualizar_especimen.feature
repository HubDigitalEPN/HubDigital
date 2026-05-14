# language: es
Característica: Actualización de datos de especímenes
  Como curador de la colección entomológica,
  quiero corregir o completar los datos de campo de un especímen,
  para mantener información precisa sin necesidad de eliminarlo.

  Escenario: El curador actualiza los datos de un especímen disponible
    Dado que existe un especímen disponible con localidad "Pichincha"
    Cuando el curador actualiza el especímen con localidad "Imbabura" y colector "Nuevo Colector"
    Entonces el especímen refleja la localidad "Imbabura" y el colector "Nuevo Colector"

  Escenario: El curador no puede actualizar un especímen que no existe
    Dado que no existe ningún especímen en el sistema
    Cuando el curador intenta actualizar un especímen inexistente
    Entonces el sistema informa que el especímen no fue encontrado
