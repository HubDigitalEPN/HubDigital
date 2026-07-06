# language: es
# Feature: 4
@listo
Característica: Recepción de muestras físicas
    Como curador
    Quiero registrar la evaluación física de los lotes entregados
    Para ingresar a la colección los especímenes de las solicitudes aprobadas

    Antecedentes:
        Dado que el investigador entrega el lote físico de una solicitud "Aprobada Documentalmente"
        Y el curador accede a los datos de la solicitud a partir de su Código QR

    Esquema del escenario: Recepción física conforme cuando el lote supera la lista de verificación
        Dado que la solicitud es un trámite de "<tipo_tramite>"
        Y el lote cumple todos los ítems de la lista de verificación de recepción:
            | Ítem de verificación       | Resultado |
            | Nivel de alcohol           | Adecuado  |
            | Estado de los especímenes  | Sanos     |
            | Integridad de los frascos  | Íntegros  |
            | Completitud del conteo     | Exacto    |
        Cuando el curador aprueba la recepción del lote
        Entonces el lote pasa a estado "Verificado Físicamente"
        Y se emite el "Acta Digital de Recepción"
        Y los especímenes asociados ingresan a la colección en estado "<estado_coleccion>"
        Y se notifica al investigador la finalización exitosa de la entrega

        Ejemplos:
            | tipo_tramite | estado_coleccion |
            | Depósito     | Temporal         |
            | Donación     | Permanente       |

    Esquema del escenario: Rechazo y devolución al investigador cuando la anomalía es subsanable
        Cuando el curador registra el fallo de integridad subsanable "<motivo_fallo>"
        Entonces el curador rechaza la recepción del lote
        Y el lote pasa a estado "Recepción Suspendida"
        Y se emite una orden de "<accion_correctiva>" para el investigador
        Y el Código QR permanece vigente para reintentar la recepción del mismo lote

        Ejemplos:
            | motivo_fallo                  | accion_correctiva       |
            | Nivel de alcohol insuficiente | Corrección en sitio     |
            | Frascos rotos                 | Reemplazo de material   |
            | Inconsistencia en conteo      | Auditoría de inventario |

    Esquema del escenario: Aceptación con observaciones cuando la anomalía no puede devolverse
        Dado que la solicitud es un trámite de "<tipo_tramite>"
        Y el lote presenta la anomalía "<tipo_observacion>" que no es posible devolver al investigador
        Cuando el curador acepta la recepción del lote con observaciones
        Entonces el lote pasa a estado "Verificado con Observaciones"
        Y las observaciones quedan registradas en el "Acta Digital de Recepción"
        Y los especímenes asociados ingresan a la colección en estado "<estado_coleccion>"
        Y se notifica al investigador la finalización de la entrega con observaciones

        Ejemplos:
            | tipo_tramite | tipo_observacion            | estado_coleccion |
            | Depósito     | Contaminación por infección | Cuarentena       |
            | Donación     | Contaminación por infección | Cuarentena       |
            | Depósito     | Otras observaciones         | Temporal         |
            | Donación     | Otras observaciones         | Permanente       |
