# language: es
Característica: Desactivación de taxones en el árbol taxonómico
  Como curador responsable de la colección,
  quiero desactivar taxones que ya no están vigentes,
  para mantener el árbol taxonómico limpio sin eliminar información histórica.

  Escenario: El curador desactiva un taxón activo
    Dado que existe un taxón activo en el árbol taxonómico
    Cuando el curador desactiva el taxón
    Entonces el taxón queda en estado inactivo

  Escenario: El curador no puede desactivar un taxón que no existe
    Dado que no existe el taxón en el árbol taxonómico
    Cuando el curador intenta desactivar un taxón inexistente
    Entonces el sistema informa que el taxón no fue encontrado
