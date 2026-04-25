# language: es
Característica: Revisión de la matriz de especies
    Como investigador o representante de colección
    Quiero validar la integridad técnica y taxonómica de la matriz Darwin Core
    Para asegurar que los datos de los especímenes cumplan con los estándares científicos de la colección

    Antecedentes:
        Dado que el investigador ha iniciado una solicitud en el sistema
        Y se ha cargado una matriz en formato Darwin Core (DwC)

    # Este escenario aplica para ambos trámites (Depósito y Donación)
    Esquema del escenario: Validación de campos obligatorios dinámicos (DwC)
        Dado que el campo "<campo_dwc>" ha sido configurado como obligatorio por curaduría
        Cuando se evalúa una matriz donde el campo "<campo_dwc>" está vacío
        Entonces la carga de la matriz es rechazada
        Y se indica al investigador que el campo "<campo_dwc>" es requerido para el estándar de la colección

        Ejemplos:
            | campo_dwc       |
            | decimalLatitude |
            | verbatimDepth   |
            | habitat         |

    @deposito
    Esquema del escenario: Validación taxonómica con sugerencias del catálogo de referencia
        Dado que el trámite es un "Depósito" de recolección en campo
        Y la matriz contiene la especie "<especie_ingresada>"
        Y el catálogo taxonómico de referencia detecta una posible inconsistencia tipográfica
        Cuando el investigador acepta la sugerencia "<especie_sugerida>"
        Entonces el registro taxonómico se marca como "Corregido por Sugerencia"
        Y el estado del registro pasa a "Validado Técnicamente"

        Ejemplos:
            | especie_ingresada | especie_sugerida |
            | Apis melifera     | Apis mellifera   |
            | Panthera oncae    | Panthera onca    |

    @deposito
    Esquema del escenario: Justificación de hallazgos taxonómicos no catalogados
        Dado que el trámite es un "Depósito" de recolección en campo
        Y la matriz contiene la especie no registrada "<especie>"
        Cuando el investigador justifica el registro indicando el motivo "<motivo_justificacion>"
        Entonces el registro queda marcado para "Validación Manual por Curaduría"
        Y la matriz asume el estado general de "Cargada con Alertas"

        Ejemplos:
            | especie           | motivo_justificacion   |
            | Morpho sp. nov.   | Es una Especie Nueva   |
            | Insecta incognita | No listada en catálogo |

    @donacion
    Escenario: Aceptación de taxonomía por transferencia de colecciones
        Dado que el trámite es una "Donación"
        Y los especímenes provienen de una colección biológica previamente establecida
        Cuando se evalúa la matriz Darwin Core
        Entonces se omite la validación de inconsistencias tipográficas contra el catálogo
        Y se conserva la identificación taxonómica original de la colección donante
        Y la matriz asume automáticamente el estado de "Validada Técnicamente"
