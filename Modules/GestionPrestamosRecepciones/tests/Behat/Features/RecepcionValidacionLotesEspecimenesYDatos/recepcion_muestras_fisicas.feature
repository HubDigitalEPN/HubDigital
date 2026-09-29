# language: es
# Feature: 4
@listo
Característica: Recepción de muestras físicas
    Como responsable de recepción EPN y curador
    Quiero registrar la evaluación física de los lotes entregados
    Para documentar la custodia y accesionar solo lotes con acta final firmada

    Antecedentes:
        Dado que el investigador entrega el lote físico de una solicitud "Aprobada Documentalmente"
        Y el receptor EPN accede a los datos de la solicitud a partir de su Código QR

    Escenario: Recepción conforme de una donación incorpora el lote de forma permanente
        Dado que la solicitud es un trámite de "Donación"
        Y el lote cumple todos los ítems de la lista de verificación de recepción:
            | Ítem de verificación       | Resultado |
            | Nivel de alcohol           | Adecuado  |
            | Estado de los especímenes  | Sanos     |
            | Integridad de los frascos  | Íntegros  |
            | Completitud del conteo     | Exacto    |
        Cuando el receptor EPN constata la recepción del lote
        Entonces el lote pasa a estado "Verificado Físicamente"
        Y el acta final queda pendiente de curaduría
        Y los especímenes todavía no ingresan a la colección
        Cuando el curador genera y firma electrónicamente el acta final
        Y los especímenes asociados ingresan a la colección en estado "Permanente"
        Y se notifica al investigador la finalización exitosa de la entrega

    Esquema del escenario: Rechazo y devolución al investigador cuando la anomalía es subsanable
        Cuando el receptor EPN registra el fallo de integridad subsanable "<motivo_fallo>"
        Entonces el receptor EPN rechaza la recepción del lote
        Y el lote pasa a estado "Recepción Suspendida"
        Y se emite una orden de "<accion_correctiva>" para el investigador
        Y el Código QR permanece vigente para reintentar la recepción del mismo lote

        Ejemplos:
            | motivo_fallo                  | accion_correctiva       |
            | Nivel de alcohol insuficiente | Corrección en sitio     |
            | Frascos rotos                 | Reemplazo de material   |
            | Inconsistencia en conteo      | Auditoría de inventario |

    Esquema del escenario: Aceptación con observaciones según los ítems no conformes del lote
        Dado que la solicitud es un trámite de "<tipo_tramite>"
        Y el lote presenta el ítem no conforme "<item_no_conforme>"
        Cuando el receptor EPN acepta la recepción del lote con observaciones
        Entonces el lote pasa a estado "Verificado con Observaciones"
        Y las observaciones quedan registradas para el "Acta Digital de Recepción"
        Y los especímenes todavía no ingresan a la colección
        Cuando el curador genera y firma electrónicamente el acta final
        Y los especímenes asociados ingresan a la colección en estado "<estado_coleccion>"
        Y se notifica al investigador la finalización de la entrega con observaciones

        Ejemplos:
            | tipo_tramite | item_no_conforme          | estado_coleccion |
            | Depósito     | Estado de los especímenes | Cuarentena       |
            | Donación     | Estado de los especímenes | Cuarentena       |
            | Depósito     | Integridad de los frascos | Temporal         |
            | Donación     | Integridad de los frascos | Permanente       |
