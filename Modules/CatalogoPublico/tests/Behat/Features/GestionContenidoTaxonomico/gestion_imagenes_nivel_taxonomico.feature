#language: es

Característica: Gestión y ordenamiento de múltiples imágenes por nivel taxonómico
    Como curador de la colección entomológica
    Quiero asignar múltiples fotografías a cualquier nivel del árbol taxonómico y definir su orden de aparición
    Para controlar qué imagen se despliega primero en la galería pública de divulgación

    Antecedentes:
        Dado que la estructura taxonómica ya existe en la tabla de divulgación:
            | phylum     | class   | order       | family     | genus | specificEpithet | occurrenceID |
            | Arthropoda | Insecta | Hymenoptera | Formicidae | Atta  | cephalotes      | EPN-0012     |

    Esquema del escenario: Asignar y ordenar imágenes en cada nivel taxonómico
        Cuando el curador asocia las siguientes imágenes al nivel "<nivel_taxón>" con el valor "<valor_taxón>":
            | archivo_imagen | orden_aparicion |
            | <foto_A>       | 2               |
            | <foto_B>       | 1               |
            | <foto_C>       | 3               |
        Entonces la base de datos de divulgación debe almacenar las imágenes indexadas por su orden:
            | nivel_taxonómico | valor_taxón     | archivo_imagen | orden_aparicion |
            | <nivel_taxón>    | <valor_taxón>   | <foto_B>       | 1               |
            | <nivel_taxón>    | <valor_taxón>   | <foto_A>       | 2               |
            | <nivel_taxón>    | <valor_taxón>   | <foto_C>       | 3               |
        Y al consultar la galería pública de "<valor_taxón>", la primera imagen devuelta debe ser "<foto_B>"

        Ejemplos:
            | nivel_taxón     | valor_taxón     | foto_A                 | foto_B                 | foto_C                 |
            | phylum          | Arthropoda      | arth_anatomia.jpg      | arth_portada.jpg       | arth_diversidad.jpg    |
            | class           | Insecta         | ins_alas.jpg           | ins_portada.jpg        | ins_patas.jpg          |
            | order           | Hymenoptera     | hym_comportamiento.jpg | hym_portada.jpg        | hym_colonia.jpg        |
            | family          | Formicidae      | form_obreras.jpg       | form_portada.jpg       | form_larvas.jpg        |
            | genus           | Atta            | atta_vuelo.jpg         | atta_reina.jpg         | atta_nido.jpg          |
            | specificEpithet | Atta cephalotes | cephalotes_soldado.jpg | cephalotes_pupa.jpg    | cephalotes_hongo.jpg   |
            | specimen        | EPN-0012        | EPN0012_lateral.jpg    | EPN0012_dorsal.jpg     | EPN0012_etiqueta.jpg   |

    Esquema del escenario: Prevenir duplicación del orden de aparición en el mismo taxón
        Cuando el curador intenta asignar el orden <orden_duplicado> a dos imágenes distintas en el nivel "<nivel_taxón>" para "<valor_taxón>"
        Entonces el sistema debe rechazar la operación debido a un conflicto de ordenamiento
        Y la base de datos no debe guardar los cambios

        Ejemplos:
            | nivel_taxón     | valor_taxón | orden_duplicado |
            | phylum          | Arthropoda  | 1               |
            | class           | Insecta     | 1               |
            | order           | Hymenoptera | 2               |
            | family          | Formicidae  | 1               |
            | genus           | Atta        | 3               |
            | specificEpithet | Atta cephalotes | 1           |
            | specimen        | EPN-0012    | 2               |
