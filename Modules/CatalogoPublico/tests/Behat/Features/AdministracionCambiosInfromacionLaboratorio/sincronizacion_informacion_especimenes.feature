#language: es

Característica: Sincronización de información divulgable de especímenes
    Como curador de la colección entomológica
    Quiero sincronizar especímenes desde la base de información interna hacia la tabla de divulgación
    Para controlar qué información de los especímenes estará disponible para consulta pública

    Antecedentes:
        Dado que existen los siguientes especímenes en la base de información interna del laboratorio:
            | occurrenceID | scientificName    | individualCount | typeStatus | typeNotes         | specimenNotes            | samplingProtocol | recordedBy      | occurrenceStatus | family      | genus  | country | localityName    | decimalLatitude | decimalLongitude |
            | EPN-0012     | Atta cephalotes   | 3               | Holotype   | Espécimen tipo    | Obrera recolectada       | Trampa Winkler   | Ana Torres      | present          | Formicidae  | Atta   | Ecuador | Reserva Yasuní   | -0.6753         | -76.3981         |
            | EPN-002      | Bombus atratus    | 1               | Paratype   | Serie comparativa | Individuo en préstamo    | Red entomológica | Carlos Mena     | loaned           | Apidae      | Bombus | Ecuador | Papallacta       | -0.3764         | -78.1419         |
            | EPN-005      | Morpho menelaus   | 2               |            |                   | Alas preservadas         | Captura manual   | Laboratorio EPN | present          | Nymphalidae | Morpho | Ecuador | Mindo            | -0.0512         | -78.7783         |

    Escenario: Sincronizar nuevos especímenes con todos los datos divulgables habilitados
        Dado que los siguientes especímenes no existen en la tabla de divulgación:
            | occurrenceID |
            | EPN-0012     |
            | EPN-002      |
            | EPN-005      |
        Cuando el curador sincroniza los siguientes especímenes para divulgación:
            | occurrenceID |
            | EPN-0012     |
            | EPN-002      |
            | EPN-005      |
        Entonces los siguientes especímenes deben existir en la tabla de divulgación:
            | occurrenceID |
            | EPN-0012     |
            | EPN-002      |
            | EPN-005      |
        Y los datos divulgables de los especímenes deben quedar habilitados:
            | occurrenceID | occurrenceIDVisible | scientificNameVisible | individualCountVisible | typeStatusVisible | typeNotesVisible | specimenNotesVisible | samplingProtocolVisible | recordedByVisible | occurrenceStatusVisible | familyVisible | genusVisible | countryVisible | localityNameVisible | decimalLatitudeVisible | decimalLongitudeVisible |
            | EPN-0012     | true                | true                  | true                   | true              | true             | true                 | true                    | true              | true                    | true          | true         | true           | true                | true                   | true                    |
            | EPN-002      | true                | true                  | true                   | true              | true             | true                 | true                    | true              | true                    | true          | true         | true           | true                | true                   | true                    |
            | EPN-005      | true                | true                  | true                   | true              | true             | true                 | true                    | true              | true                    | true          | true         | true           | true                | true                   | true                    |

    Escenario: Sincronizar nuevos especímenes especificando datos divulgables
        Dado que los siguientes especímenes no existen en la tabla de divulgación:
            | occurrenceID |
            | EPN-0012     |
            | EPN-002      |
            | EPN-005      |
        Cuando el curador sincroniza los siguientes especímenes para divulgación con esta configuración:
            | occurrenceID | scientificName | individualCount | typeStatus | typeNotes | specimenNotes | samplingProtocol | recordedBy | occurrenceStatus | family | genus | country | localityName | decimalLatitude | decimalLongitude |
            | EPN-0012     | true           | true            | true       | false     | false         | true             | true       | true             | true   | true  | true    | true         | false           | false            |
            | EPN-002      | true           | true            | true       | false     | false         | true             | true       | true             | true   | true  | true    | true         | false           | false            |
            | EPN-005      | true           | true            | true       | false     | false         | true             | true       | true             | true   | true  | true    | true         | false           | false            |
        Entonces los siguientes especímenes deben existir en la tabla de divulgación:
            | occurrenceID |
            | EPN-0012     |
            | EPN-002      |
            | EPN-005      |
        Y los datos divulgables de los especímenes deben quedar configurados:
            | occurrenceID | scientificName | individualCount | typeStatus | typeNotes | specimenNotes | samplingProtocol | recordedBy | occurrenceStatus | family | genus | country | localityName | decimalLatitude | decimalLongitude |
            | EPN-0012     | true           | true            | true       | false     | false         | true             | true       | true             | true   | true  | true    | true         | false           | false            |
            | EPN-002      | true           | true            | true       | false     | false         | true             | true       | true             | true   | true  | true    | true         | false           | false            |
            | EPN-005      | true           | true            | true       | false     | false         | true             | true       | true             | true   | true  | true    | true         | false           | false            |

    Escenario: Modificar los datos divulgables de especímenes ya sincronizados
        Dado que los siguientes especímenes existen en la tabla de divulgación:
            | occurrenceID | scientificName | individualCount | typeStatus | typeNotes | specimenNotes | samplingProtocol | recordedBy | occurrenceStatus | family | genus | country | localityName | decimalLatitude | decimalLongitude |
            | EPN-0012     | true           | true            | true       | true      | true          | true             | true       | true             | true   | true  | true    | true         | true            | true             |
            | EPN-002      | true           | true            | true       | true      | true          | true             | true       | true             | true   | true  | true    | true         | true            | true             |
            | EPN-005      | true           | true            | true       | true      | true          | true             | true       | true             | true   | true  | true    | true         | true            | true             |
        Cuando el curador modifica la configuración de divulgación de los siguientes especímenes:
            | occurrenceID | scientificName | individualCount | typeStatus | typeNotes | specimenNotes | samplingProtocol | recordedBy | occurrenceStatus | family | genus | country | localityName | decimalLatitude | decimalLongitude |
            | EPN-0012     | true           | true            | true       | false     | false         | true             | false      | true             | true   | true  | true    | false        | false           | false            |
            | EPN-002      | true           | true            | true       | false     | false         | true             | false      | true             | true   | true  | true    | false        | false           | false            |
            | EPN-005      | true           | true            | true       | false     | false         | true             | false      | true             | true   | true  | true    | false        | false           | false            |
        Entonces los datos divulgables de los especímenes deben quedar configurados:
            | occurrenceID | scientificName | individualCount | typeStatus | typeNotes | specimenNotes | samplingProtocol | recordedBy | occurrenceStatus | family | genus | country | localityName | decimalLatitude | decimalLongitude |
            | EPN-0012     | true           | true            | true       | false     | false         | true             | false      | true             | true   | true  | true    | false        | false           | false            |
            | EPN-002      | true           | true            | true       | false     | false         | true             | false      | true             | true   | true  | true    | false        | false           | false            |
            | EPN-005      | true           | true            | true       | false     | false         | true             | false      | true             | true   | true  | true    | false        | false           | false            |
        Y al consultar la información divulgada del espécimen "EPN-0012" solo debe estar disponible la información:
            | dato             | valor            |
            | occurrenceID     | EPN-0012         |
            | scientificName   | Atta cephalotes  |
            | individualCount  | 3                |
            | typeStatus       | Holotype         |
            | samplingProtocol | Trampa Winkler   |
            | occurrenceStatus | present          |
            | family           | Formicidae       |
            | genus            | Atta             |
            | country          | Ecuador          |
