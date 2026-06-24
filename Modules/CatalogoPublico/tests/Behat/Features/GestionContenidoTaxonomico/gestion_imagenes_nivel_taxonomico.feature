# language: es

Característica: Gestion imagenes nivel taxonomico
    Como curador de la colección entomológica
    Quiero subir imágenes vinculadas a un espécimen, registrando quién las subió,
    y elegir la imagen por defecto únicamente en los niveles de género y especie
    Para controlar qué fotografía representa cada taxón en la galería pública de divulgación

    Antecedentes:
        Dado que existe la siguiente estructura taxonómica en la tabla de divulgación:
            | phylum     | class   | order       | family     | genus | specificEpithet | occurrenceID |
            | Arthropoda | Insecta | Hymenoptera | Formicidae | Atta  | cephalotes      | EPN-0012     |

    Escenario: Subir una imagen exige nombre y apellido de quien la sube
        Cuando el curador sube la imagen "cephalotes_dorsal.jpg" al espécimen "EPN-0012"
        Y proporciona el nombre "María" y el apellido "Gómez"
        Entonces la imagen debe quedar vinculada al espécimen "EPN-0012"
        Y se debe generar una marca de agua con el texto "María Gómez" sobre la imagen
        Y la imagen debe registrar como autor a "María Gómez"

    Escenario: Rechazar la subida si falta el nombre o el apellido del autor
        Cuando el curador sube la imagen "cephalotes_lateral.jpg" al espécimen "EPN-0012"
        Y no proporciona el nombre o el apellido de quien la sube
        Entonces el sistema debe rechazar la subida
        Y la imagen no debe almacenarse

    Esquema del escenario: La primera imagen subida del subárbol se vuelve la imagen por defecto del nivel
        Dado que aún no existen imágenes para el nivel "<nivel_taxón>" con valor "<valor_taxón>"
        Cuando se sube la imagen "<primera_imagen>" como la primera del subárbol de "<valor_taxón>"
        Y posteriormente se sube la imagen "<segunda_imagen>" al mismo subárbol
        Entonces la imagen por defecto del nivel "<nivel_taxón>" para "<valor_taxón>" debe ser "<primera_imagen>"
        Y al consultar la galería pública de "<valor_taxón>", la primera imagen devuelta debe ser "<primera_imagen>"

        Ejemplos:
            | nivel_taxón     | valor_taxón     | primera_imagen      | segunda_imagen       |
            | genus           | Atta            | atta_reina.jpg      | atta_nido.jpg        |
            | specificEpithet | Atta cephalotes | cephalotes_pupa.jpg | cephalotes_hongo.jpg |

    Escenario: La imagen subida a un espécimen se vuelve el defecto de su especie y su género
        Dado que no existe ninguna imagen en el subárbol de "Atta cephalotes"
        Cuando el curador sube la imagen "cephalotes_pupa.jpg" al espécimen "EPN-0012"
        Entonces la imagen por defecto debe ser "cephalotes_pupa.jpg" únicamente en los niveles:
            | nivel_taxón     | valor_taxón     |
            | specificEpithet | Atta cephalotes |
            | genus           | Atta            |

    Esquema del escenario: El curador puede sobrescribir la imagen por defecto en género y especie
        Dado que el subárbol de "<valor_taxón>" contiene las imágenes:
            | archivo_imagen   |
            | <imagen_inicial> |
            | <imagen_elegida> |
        Y la imagen por defecto actual es "<imagen_inicial>"
        Cuando el curador selecciona "<imagen_elegida>" como imagen por defecto del nivel "<nivel_taxón>" para "<valor_taxón>"
        Entonces la imagen por defecto del nivel "<nivel_taxón>" para "<valor_taxón>" debe ser "<imagen_elegida>"
        Y al consultar la galería pública de "<valor_taxón>", la primera imagen devuelta debe ser "<imagen_elegida>"

        Ejemplos:
            | nivel_taxón     | valor_taxón     | imagen_inicial      | imagen_elegida       |
            | genus           | Atta            | atta_reina.jpg      | atta_nido.jpg        |
            | specificEpithet | Atta cephalotes | cephalotes_pupa.jpg | cephalotes_hongo.jpg |

    Esquema del escenario: El sistema no admite imágenes por defecto en niveles superiores a género
        Dado que el subárbol de "Atta cephalotes" contiene la imagen "cephalotes_pupa.jpg"
        Cuando el curador intenta seleccionar "cephalotes_pupa.jpg" como imagen por defecto del nivel "<nivel_taxón>" para "<valor_taxón>"
        Entonces el sistema debe rechazar la operación
        Y no debe existir imagen por defecto para el nivel "<nivel_taxón>" con valor "<valor_taxón>"

        Ejemplos:
            | nivel_taxón | valor_taxón |
            | phylum      | Arthropoda  |
            | class       | Insecta     |
            | order       | Hymenoptera |
            | family      | Formicidae  |
