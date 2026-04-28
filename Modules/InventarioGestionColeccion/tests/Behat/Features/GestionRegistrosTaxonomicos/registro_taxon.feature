#language: es
Característica: Registro de taxones en el árbol taxonómico
  Como curador responsable de la colección,
  quiero registrar y editar nodos en el árbol taxonómico,
  para mantener una nomenclatura centralizada y actualizada.

  Escenario: Crear un nuevo taxon en el árbol taxonómico
    Dado que el curador tiene los datos de un nuevo taxon
    Cuando el curador registra el taxon
    Entonces el taxon queda registrado en estado activo

  Escenario: Editar un taxon existente
    Dado que existe un taxon registrado en el sistema
    Cuando el curador actualiza los datos del taxon
    Entonces el taxon refleja la información actualizada

  Escenario: No se puede crear un taxon con rango inválido
    Dado que el curador tiene datos de un taxon con rango inválido
    Cuando el curador registra el taxon
    Entonces el sistema rechaza el registro con un error de validación de rango
