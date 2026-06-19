# language: es

Característica: Exportación de registros de especímenes en formato XLSX
    Como investigador que consulta el portal de divulgación
    Quiero exportar la información de los especímenes de una especie en formato XLSX
    Para utilizarla con fines de investigación y citación científica

    Escenario: El investigador exporta los registros completos de una especie
        Dado que los siguientes especímenes de "Atta cephalotes" están divulgados en el catálogo:
            | occurrenceID | scientificName  | typeStatus | occurrenceStatus | individualCount | localityName   | country | decimalLatitude | decimalLongitude | recordedBy  | samplingProtocol | typeNotes         | specimenNotes         |
            | EPN-001      | Atta cephalotes | Holotype   | present          | 3               | Reserva Yasuní | Ecuador | -0.67530        | -76.39810        | Ana Torres  | Trampa Winkler   | Espécimen tipo    | Obrera recolectada    |
            | EPN-002      | Atta cephalotes | Paratype   | loaned           | 1               | Papallacta     | Ecuador | -0.37640        | -78.14190        | Carlos Mena | Red entomológica | Serie comparativa | Individuo en préstamo |
        Cuando el investigador solicita exportar los especímenes de "Atta cephalotes"
        Entonces se descarga un archivo XLSX con el nombre "Atta_cephalotes_<fecha-actual>.xlsx"
        Y el archivo contiene una hoja con los encabezados en orden:
            | occurrenceID | scientificName | typeStatus | occurrenceStatus | individualCount | localityName | country | decimalLatitude | decimalLongitude | recordedBy | samplingProtocol | typeNotes | specimenNotes |
        Y el archivo contiene exactamente 2 filas de datos con la información de los especímenes divulgados

    Escenario: El investigador exporta especímenes con campos opcionales ausentes
        Dado que el siguiente espécimen de "Morpho menelaus" está divulgado sin coordenadas ni recolector:
            | occurrenceID | scientificName  | typeStatus | occurrenceStatus | individualCount | localityName | country | decimalLatitude | decimalLongitude | recordedBy | samplingProtocol | typeNotes | specimenNotes    |
            | EPN-005      | Morpho menelaus |            | present          | 2               | Mindo        | Ecuador |                 |                  |            | Captura manual   |           | Alas preservadas |
        Cuando el investigador solicita exportar los especímenes de "Morpho menelaus"
        Entonces se descarga un archivo XLSX con el nombre "Morpho_menelaus_<fecha-actual>.xlsx"
        Y el archivo contiene exactamente 1 fila de datos
        Y las celdas correspondientes a campos ausentes aparecen vacías en el archivo
