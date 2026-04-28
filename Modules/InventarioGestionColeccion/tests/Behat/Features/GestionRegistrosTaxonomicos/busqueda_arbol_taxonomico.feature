#language: es
Característica: Búsqueda avanzada en el árbol taxonómico
  Como investigador o curador,
  quiero buscar y filtrar especímenes por diferentes criterios taxonómicos,
  para localizar registros específicos eficientemente.

  Esquema del escenario: Búsqueda por criterio taxonómico
    Dado que existen especímenes registrados en el sistema
    Cuando se busca por <criterio> con el valor "<valor>"
    Entonces se retornan los registros que coinciden con la búsqueda

    Ejemplos:
      | criterio  | valor      |
      | taxon     | Morpho     |
      | localidad | Napo       |
      | estado    | disponible |

  Escenario: Búsqueda sin resultados
    Dado que existen especímenes registrados en el sistema
    Cuando se busca por taxon con el valor "EspecieInexistente"
    Entonces el sistema retorna una lista vacía sin error
