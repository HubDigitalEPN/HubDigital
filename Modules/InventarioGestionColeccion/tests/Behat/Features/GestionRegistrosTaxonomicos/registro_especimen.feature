# language: es
Característica: Registro de especímenes en la colección
  Como curador de la colección entomológica
  Quiero registrar especímenes con su información taxonómica y de recolecta
  Para mantener un inventario preciso de los especímenes disponibles

  Escenario: El curador registra un especímen con datos válidos
    Dado que existe un taxón registrado en el sistema para asociar al especímen
    Cuando el curador registra el especímen con código "COLEOP-001" y localidad "Pichincha"
    Entonces el especímen queda registrado con estado disponible

  Escenario: El curador no puede registrar un especímen con código de catálogo duplicado
    Dado que ya existe un especímen con código de catálogo "COLEOP-001"
    Cuando el curador intenta registrar otro especímen con el mismo código "COLEOP-001"
    Entonces el sistema rechaza el registro con error de código duplicado

  Escenario: El curador no puede registrar un especímen con un taxón inexistente
    Dado que no existe ningún taxón en el sistema
    Cuando el curador intenta registrar un especímen con un taxón inexistente
    Entonces el sistema rechaza el registro indicando que el taxón no fue encontrado
