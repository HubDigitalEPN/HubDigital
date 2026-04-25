# language: es
Característica: Revisión de la matriz de especies
    Como investigador
    Quiero validar la integridad técnica y taxonómica de la matriz Darwin Core
    Para asegurar que los datos de los especímenes cumplan con los estándares científicos de la colección

    Antecedentes:
        Dado que el investigador ha iniciado una solicitud de depósito
        Y se ha cargado una matriz en formato Darwin Core (DwC)

    Esquema del escenario: Validación de campos obligatorios dinámicos (DwC)
        Dado que el campo "<campo_dwc>" ha sido configurado como obligatorio por curaduría
        Cuando el sistema procesa una matriz con el campo "<campo_dwc>" vacío
        Entonces el sistema rechaza la carga de la matriz
        Y el sistema indica que el campo "<campo_dwc>" es requerido para el estándar de la colección

        Ejemplos:
            | campo_dwc       |
            | decimalLatitude |
            | verbatimDepth   |
            | habitat         |

    Esquema del escenario: Validación taxonómica con sugerencias de GBIF
        Dado que la matriz contiene la especie "<especie_ingresada>"
        Y el sistema identifica una posible inconsistencia tipográfica mediante GBIF
        Cuando el investigador acepta la sugerencia "<especie_sugerida>"
        Entonces el registro taxonómico se marca como "Corregido por Sugerencia"
        Y el estado del registro pasa a "Validado Técnicamente"

        Ejemplos:
            | especie_ingresada | especie_sugerida |
            | Apis melifera     | Apis mellifera   |
            | Panthera oncae    | Panthera onca    |

    Esquema del escenario: Justificación de hallazgos taxonómicos no catalogados
        Dado que la matriz contiene la especie desconocida "<especie>"
        Cuando el investigador selecciona el motivo "<motivo_justificacion>" para justificar el registro
        Entonces el sistema marca el registro para "Validación Manual por Curaduría"
        Y se permite finalizar la carga de la matriz con alertas

        Ejemplos:
            | especie           | motivo_justificacion   |
            | Morpho sp. nov.   | Es una Especie Nueva   |
            | Insecta incognita | No listada en catálogo |
