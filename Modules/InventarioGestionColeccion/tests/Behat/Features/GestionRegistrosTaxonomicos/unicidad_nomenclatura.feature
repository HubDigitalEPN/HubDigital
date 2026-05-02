#language: es
Característica: Unicidad de nomenclatura en el árbol taxonómico
  Como curador responsable de la colección,
  quiero que el sistema impida nombres científicos duplicados en el mismo nivel jerárquico,
  para garantizar la integridad del árbol taxonómico.

  Escenario: No se puede crear un taxon con nombre científico duplicado en el mismo nivel
    Dado que existe un taxon con nombre científico "Morpho" en el rango "genero"
    Cuando el curador intenta crear otro taxon con el mismo nombre y rango
    Entonces el sistema rechaza el registro indicando duplicado de nomenclatura

  Escenario: Se puede crear un taxon con el mismo nombre en diferente rango
    Dado que existe un taxon con nombre científico "Morpho" en el rango "genero"
    Cuando el curador crea un taxon con el mismo nombre pero en el rango "familia"
    Entonces el taxon queda registrado exitosamente en el rango "familia"
