# language: es
Característica: Recepción de muestras físicas mediante escaneo QR
    Como curador
    Quiero escanear el código QR de las solicitudes aprobadas
    Para registrar la evaluación física de los lotes entregados e ingresarlos a la colección temporal

    Antecedentes:
        Dado que el investigador ha llegado al centro de acopio con las muestras físicas
        Y presenta su Código QR de aprobación (en formato impreso o digital)

    Escenario: Recepción física exitosa e ingreso a colección temporal
        Dado que el curador escanea el Código QR del investigador
        Y el sistema carga los datos de la solicitud "Aprobada Documentalmente"
        Cuando el curador verifica el lote físico y registra su integridad como "Conforme"
        Entonces el sistema establece el estado del lote como "Verificado Físicamente"
        Y el sistema genera el "Acta Digital de Recepción"
        Y se asigna el estado "Temporal" a todos los especímenes asociados al lote
        Y el sistema notifica al investigador la finalización exitosa de la entrega

    Esquema del escenario: Suspensión de la recepción por fallos de integridad física
        Dado que el curador escanea el Código QR del investigador
        Y el sistema carga los datos de la solicitud "Aprobada Documentalmente"
        Cuando el curador evalúa las muestras y registra un fallo de integridad por "<motivo_fallo>"
        Entonces el sistema establece el estado del lote en "Recepción Suspendida"
        Y el sistema emite una orden de "<accion_correctiva>" al investigador

        Ejemplos:
            | motivo_fallo                  | accion_correctiva       |
            | Nivel de alcohol insuficiente | Corrección en sitio     |
            | Frascos rotos                 | Reemplazo de material   |
            | Inconsistencia en conteo      | Auditoría de inventario |

    Escenario: Generación de acta e ingreso a colección temporal
        Dado que el curador escanea el Código QR del investigador
        Y el sistema carga los datos de la solicitud "Aprobada Documentalmente"
        Cuando el curador verifica el lote físico y registra su integridad como "Conforme"
        Entonces el sistema establece el estado del lote como "Verificado Físicamente"
        Y el sistema genera automáticamente el "Acta Digital de Recepción" en formato PDF
        Y todos los especímenes de este lote adquieren el estado "PREVALIDADO" en la base de datos
        Y el sistema envía una notificación de "Recepción Finalizada" con el acta adjunta al investigador
