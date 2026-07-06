# language: es
# Feature: 4

Característica: Recepción de muestras físicas
    Como curador
    Quiero escanear el código QR de las solicitudes aprobadas
    Para registrar la evaluación física de los lotes entregados e ingresarlos a la colección temporal

    Antecedentes:
        Dado que el investigador presenta el Código QR de una solicitud "Aprobada Documentalmente"

    Escenario: Recepción física exitosa e ingreso a colección temporal
        Dado que el curador accede a los datos de la solicitud mediante el Código QR
        Cuando el curador verifica el lote físico y registra su integridad como "Conforme"
        Entonces el lote físico pasa a estado "Verificado Físicamente"
        Y se emite el "Acta Digital de Recepción"
        Y los especímenes asociados ingresan a la colección en estado "Temporal"
        Y se notifica al investigador la finalización exitosa de la entrega

    Esquema del escenario: Suspensión de la recepción por fallos de integridad física
        Dado que el curador accede a los datos de la solicitud mediante el Código QR
        Cuando el curador evalúa las muestras y registra un fallo de integridad por "<motivo_fallo>"
        Entonces el lote queda en estado "Recepción Suspendida"
        Y se emite una orden de "<accion_correctiva>" para el investigador

        Ejemplos:
            | motivo_fallo                  | accion_correctiva       |
            | Nivel de alcohol insuficiente | Corrección en sitio     |
            | Frascos rotos                 | Reemplazo de material   |
            | Inconsistencia en conteo      | Auditoría de inventario |
