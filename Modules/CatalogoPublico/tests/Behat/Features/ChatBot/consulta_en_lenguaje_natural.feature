#language: es

@listo
Característica: Consulta en lenguaje natural
    Como visitante del portal de divulgación
    Quiero realizar consultas en lenguaje natural
    Para adquirir información de diverso tipo con el contexto del laboratorio

    Antecedentes:
        Dado que existen los siguientes especímenes ya divulgados
            | occurrenceID | scientificName    | individualCount | typeStatus | typeNotes         | specimenNotes            | samplingProtocol | recordedBy      | occurrenceStatus | family      | genus  | country | localityName    | decimalLatitude | decimalLongitude |
            | EPN-0012     | Atta cephalotes   | 3               | Holotype   | Espécimen tipo    | Obrera recolectada       | Trampa Winkler   | Ana Torres      | present          | Formicidae  | Atta   | Ecuador | Reserva Yasuní  | -0.6753         | -76.3981         |
            | EPN-002      | Bombus atratus    | 1               | Paratype   | Serie comparativa | Individuo en préstamo    | Red entomológica | Carlos Mena     | loaned           | Apidae      | Bombus | Ecuador | Papallacta      | -0.3764         | -78.1419         |
            | EPN-005      | Morpho menelaus   | 2               |            |                   | Alas preservadas         | Captura manual   | Laboratorio EPN | present          | Nymphalidae | Morpho | Ecuador | Mindo           | -0.0512         | -78.7783         |

    Esquema del escenario: El visitante recupera información precisa por atributos
        Dado que el visitante ingresa la pregunta "<pregunta>"
        Cuando el motor de búsqueda recupera el contexto de la base de datos
        Entonces el contexto del LLM incluye el espécimen "<occurrenceID>"
        Y el contexto del LLM incluye la localidad "<localidad>" del espécimen

        Ejemplos:
            | pregunta                                            | occurrenceID | localidad      |
            | ¿Cuáles son los especímenes del género Atta?        | EPN-0012     | Reserva Yasuní |
            | Muéstrame información sobre la especie de Mindo     | EPN-005      | Mindo          |

    Esquema del escenario: El visitante consulta la síntesis de un espécimen específico
        Dado que el visitante realiza la pregunta "Sintetiza la información del espécimen <occurrenceID>"
        Cuando el motor de búsqueda recupera los datos tabulares del espécimen para el LLM
        Entonces el contexto del LLM incluye la familia "<familia>" del espécimen
        Y el contexto del LLM incluye el método de recolección "<metodo_recoleccion>"

        Ejemplos:
            | occurrenceID | familia     | metodo_recoleccion |
            | EPN-002      | Apidae      | Red entomológica   |
            | EPN-0012     | Formicidae  | Trampa Winkler     |

    Esquema del escenario: El visitante recibe rechazo ante preguntas fuera del dominio
        Dado que el visitante realiza una pregunta fuera del dominio como "<pregunta_invalida>"
        Cuando el clasificador de intención evalúa la pertinencia de la consulta
        Entonces la consulta se marca como fuera de dominio y no se envía al LLM
        Y el chatbot responde con el mensaje predeterminado de dominio no soportado

        Ejemplos:
            | pregunta_invalida                                                          |
            | ¿Cuáles son las probabilidades de que Ecuador gane el próximo mundial?     |
            | Escribe un script en Python para migrar datos a PostgreSQL                 |
            | ¿Cuál es la receta para hacer pan?                                         |
