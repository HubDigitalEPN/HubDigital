# language: es
Característica: Búsqueda multicriterio en el catálogo público
  Para localizar especímenes divulgados combinando múltiples criterios
  Como el visitante del catálogo público
  Quiero filtrar por número de catálogo, taxonomía, geografía, colector, fecha, método, coordenadas, elevación, bioma y tipo de preparación

  Antecedentes:
    Dado que existen los siguientes especímenes divulgados en el catálogo público:
      | occurrenceID | scientificName        | phylum     | class    | order       | family      | genus       | specificEpithet | country  | stateProvince | localityName    | recordedBy   | eventDate  | samplingProtocol | decimalLatitude | decimalLongitude | elevationMinM | biome              | preparations |
      | EPN-001      | Anolis proboscis      | Chordata   | Reptilia | Squamata    | Dactyloidae | Anolis      | proboscis       | Ecuador  | Pichincha     | Mindo           | J. Pérez     | 2024-03-15 | Trampa Malaise   | -0.0500         | -78.7700         | 1400          | Bosque nublado     | Húmedo       |
      | EPN-002      | Coleoptera sp1        | Arthropoda | Insecta  | Coleoptera  | Carabidae   | Carabus     | violaceus       | Ecuador  | Napo          | Tena            | M. Gómez     | 2023-07-10 | Red entomológica | -0.9900         | -77.8100         | 550           | Bosque amazónico   | Seco         |
      | EPN-003      | Morpho peleides       | Arthropoda | Insecta  | Lepidoptera | Nymphalidae | Morpho      | peleides        | Ecuador  | Pichincha     | Nono            | J. Pérez     | 2024-05-02 | Red entomológica | -0.0700         | -78.5900         | 2300          | Bosque nublado     | Seco         |
      | EPN-004      | Atta cephalotes       | Arthropoda | Insecta  | Hymenoptera | Formicidae  | Atta        | cephalotes      | Ecuador  | Napo          | Yasuní          | L. Torres    | 2022-11-20 | Pitfall          | -0.9800         | -76.4000         | 250           | Bosque amazónico   | Seco         |

  Escenario: Búsqueda por número de catálogo único
    Cuando el visitante aplica los filtros con "filtroCatalogo" igual a "EPN-002"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-002      |

  Escenario: Búsqueda por múltiples números de catálogo separados por coma
    Cuando el visitante aplica los filtros con "filtroCatalogo" igual a "EPN-001, EPN-003"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |
      | EPN-003      |

  Esquema del escenario: Búsqueda taxonómica a cualquier nivel del árbol
    Cuando el visitante aplica los filtros con "filtroTaxon" igual a "<termino>"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID   |
      | <esperado>     |

    Ejemplos:
      | termino     | esperado |
      | Insecta     | EPN-002  |
      | Insecta     | EPN-003  |
      | Insecta     | EPN-004  |
      | Coleoptera  | EPN-002  |
      | Morpho      | EPN-003  |

  Escenario: La búsqueda taxonómica ignora mayúsculas y acentos
    Cuando el visitante aplica los filtros con "filtroTaxon" igual a "cóleoptera"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-002      |

  Escenario: Búsqueda por geografía con múltiples localidades
    Cuando el visitante aplica los filtros con "filtroGeografias" igual a "Pichincha, Napo"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |
      | EPN-002      |
      | EPN-003      |
      | EPN-004      |

  Escenario: Búsqueda por colector con coincidencia parcial
    Cuando el visitante aplica los filtros con "filtroColector" igual a "Pérez"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |
      | EPN-003      |

  Escenario: Búsqueda por rango de fechas de recolección
    Cuando el visitante aplica los filtros:
      | campo            | valor      |
      | filtroFechaDesde | 2024-01-01 |
      | filtroFechaHasta | 2024-12-31 |
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |
      | EPN-003      |

  Escenario: Búsqueda por método de recolección (selección múltiple)
    Cuando el visitante aplica los filtros con "filtroMetodos" igual a "Red entomológica, Pitfall"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-002      |
      | EPN-003      |
      | EPN-004      |

  Escenario: Búsqueda por tipo de preparación (selección múltiple)
    Cuando el visitante aplica los filtros con "filtroPreparaciones" igual a "Húmedo"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |

  Escenario: Búsqueda por bioma
    Cuando el visitante aplica los filtros con "filtroBiomas" igual a "Bosque nublado"
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |
      | EPN-003      |

  Escenario: Búsqueda por coordenadas aplica un radio de ±0.5°
    Cuando el visitante aplica los filtros:
      | campo         | valor     |
      | filtroLatMin  | -0.5700   |
      | filtroLatMax  |  0.4300   |
      | filtroLonMin  | -79.0700  |
      | filtroLonMax  | -78.0700  |
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |
      | EPN-003      |

  Escenario: Búsqueda por rango de elevación
    Cuando el visitante aplica los filtros:
      | campo            | valor |
      | filtroElevDesde  | 1000  |
      | filtroElevHasta  | 2000  |
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |

  Escenario: Combinación de criterios (taxonomía + provincia + colector)
    Cuando el visitante aplica los filtros:
      | campo              | valor    |
      | filtroTaxon        | Insecta  |
      | filtroGeografias   | Pichincha|
      | filtroColector     | Pérez    |
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-003      |

  Escenario: Combinación de criterios sin coincidencias
    Cuando el visitante aplica los filtros:
      | campo            | valor       |
      | filtroTaxon      | Reptilia    |
      | filtroGeografias | Napo        |
    Entonces el resultado no contiene ningún espécimen

  Escenario: Limpiar filtros restablece el listado completo
    Dado que el visitante ha aplicado los filtros con "filtroCatalogo" igual a "EPN-001"
    Cuando el visitante limpia los filtros
    Entonces el resultado contiene exactamente los especímenes:
      | occurrenceID |
      | EPN-001      |
      | EPN-002      |
      | EPN-003      |
      | EPN-004      |
