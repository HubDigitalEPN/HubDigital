#language: es

Característica: Presentación de árbol taxonómico en la página de divulgación
    Como usuario visitante de la plataforma de divulgación
    Quiero ver los especímenes organizados en un árbol taxonómico de 6 niveles
    Para navegar e identificar el contenido biológico divulgado de la colección entomológica

    Antecedentes:
        Dado que la tabla de divulgación contiene los siguientes especímenes con sus datos de jerarquía taxonómica:
            | occurrenceID | phylum     | class   | order       | family      | genus   | specificEpithet | scientificName    |
            | EPN-0012     | Arthropoda | Insecta | Hymenoptera | Formicidae  | Atta    | cephalotes      | Atta cephalotes   |
            | EPN-002      | Arthropoda | Insecta | Hymenoptera | Apidae      | Bombus  | atratus         | Bombus atratus    |
            | EPN-005      | Arthropoda | Insecta | Lepidoptera | Nymphalidae | Morpho  | menelaus        | Morpho menelaus   |
        Y la configuración de visibilidad para estos especímenes tiene habilitados los campos taxonómicos (true)

    Escenario: Generar la estructura del árbol taxonómico con nodos compartidos y nombres de especies correctos
        Cuando el sistema procesa los especímenes para construir el árbol de divulgación
        Entonces la estructura jerárquica resultante debe agrupar los nodos de la siguiente manera:
            | nivel           | taxon       | padre       |
            | phylum          | Arthropoda  | root        |
            | class           | Insecta     | Arthropoda  |
            | order           | Hymenoptera | Insecta     |
            | order           | Lepidoptera | Insecta     |
            | family          | Formicidae  | Hymenoptera |
            | family          | Apidae      | Hymenoptera |
            | family          | Nymphalidae | Lepidoptera |
            | genus           | Atta        | Formicidae  |
            | genus           | Bombus      | Apidae      |
            | genus           | Morpho      | Nymphalidae |
        Y las ramas finales (Species) deben formarse por la unión de Genus + Specific epithet:
            | especie          | genus  | specificEpithet | padre (genus) |
            | Atta cephalotes  | Atta   | cephalotes      | Atta          |
            | Bombus atratus   | Bombus | atratus         | Bombus        |
            | Morpho menelaus  | Morpho | menelaus        | Morpho        |

    Escenario: Asignar los especímenes correspondientes a las hojas del árbol taxonómico
        Cuando el usuario consulta el árbol taxonómico de divulgación
        Entonces debajo del nodo de cada especie se deben listar sus respectivos especímenes:
            | especie          | especímenes_asociados |
            | Atta cephalotes  | EPN-0012              |
            | Bombus atratus   | EPN-002               |
            | Morpho menelaus  | EPN-005               |

    Escenario: Exclusión de especímenes no visibles en el árbol
        Dado que se cambia la configuración de divulgación y el espécimen "EPN-005" tiene 'genusVisible' o 'scientificNameVisible' en false
        Cuando el sistema renderiza el árbol taxonómico para el público
        Entonces el nodo de la especie "Morpho menelaus" y su espécimen "EPN-005" no deben aparecer en el árbol
        Y los nodos superiores huérfanos sin otros hijos divulgados (como la familia "Nymphalidae" si no tiene más miembros) deben quedar ocultos
