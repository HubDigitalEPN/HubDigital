# language: es
# Feature: 2
Característica: Revisión de la matriz de especies
    Como investigador
    Quiero validar la integridad técnica y taxonómica de la matriz Darwin Core
    Para asegurar que los datos de los especímenes cumplan con los estándares científicos de la colección

    Antecedentes:
        Dado que un investigador ha validado la documentación legal de su solicitud
        Y la solicitud está pendiente de la asignación de una matriz de especímenes

    Escenario: El investigador no puede finalizar la solicitud sin matriz
        Dado que la solicitud no tiene una matriz de especímenes asociada
        Cuando el investigador intenta finalizar el envío de la solicitud
        Entonces el sistema rechaza el envío
        Y se le notifica que la matriz de especímenes es obligatoria

    # Este escenario aplica para ambos trámites (Depósito y Donación)
    Esquema del escenario: Validación de campos dinámicos Darwin Core (DwC)
        Dado que el catálogo de curaduría exige el campo "<campo_dwc>"
        Y la matriz ingresada carece del campo "<campo_dwc>"
        Cuando el investigador intenta cargar la matriz en el sistema
        Entonces el sistema rechaza la carga
        Y se notifica al investigador que "<campo_dwc>" es requerido por la colección

        Ejemplos:
            | campo_dwc       |
            | decimalLatitude |
            | verbatimDepth   |
            | habitat         |

    @deposito
    Esquema del escenario: Validación taxonómica con sugerencias del catálogo de referencia
        Dado que la matriz de recolección contiene la especie "<especie_ingresada>"
        Y el sistema detecta una posible inconsistencia tipográfica en el catálogo
        Cuando el investigador acepta la sugerencia de corrección a "<especie_sugerida>"
        Entonces el registro se actualiza con el estado "Corregido por Sugerencia"
        Y el registro queda marcado como "Validado Técnicamente"

        Ejemplos:
            | especie_ingresada | especie_sugerida |
            | Apis melifera     | Apis mellifera   |
            | Panthera oncae    | Panthera onca    |

    @deposito
    Esquema del escenario: Justificación de hallazgos taxonómicos no catalogados
        Dado que la matriz de recolección contiene la especie no registrada "<especie>"
        Cuando el investigador justifica el hallazgo con el motivo "<motivo_justificacion>"
        Entonces el registro se etiqueta para "Validación Manual por Curaduría"
        Y la matriz asume el estado de "Cargada con Alertas"

        Ejemplos:
            | especie           | motivo_justificacion   |
            | Morpho sp. nov.   | Es una Especie Nueva   |
            | Insecta incognita | No listada en catálogo |

    @deposito
    Escenario: Avance de solicitud con alertas taxonómicas justificadas
        Dado que la matriz de recolección tiene el estado "Cargada con Alertas"
        Y el investigador ha justificado todos los hallazgos no catalogados
        Cuando el investigador intenta finalizar el envío de la solicitud
        Entonces el sistema procesa el envío exitosamente
        Y las alertas se derivan a la bandeja de revisión manual de curaduría

    @donacion
    Escenario: Aceptación automática de taxonomía por transferencia de colecciones
        Dado que la solicitud es una transferencia por "Donación" de una colección establecida
        Cuando el investigador carga la matriz en formato Darwin Core
        Entonces el sistema omite la validación de inconsistencias tipográficas
        Y se conserva la identificación taxonómica original de forma íntegra
        Y la matriz asume el estado de "Validada Técnicamente"
