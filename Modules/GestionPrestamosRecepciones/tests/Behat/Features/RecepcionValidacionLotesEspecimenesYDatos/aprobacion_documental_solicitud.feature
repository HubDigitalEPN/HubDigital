# language: es
# Feature: 3
Característica: Aprobación documental de la solicitud
    Como curador
    Quiero revisar y decidir sobre las solicitudes de depósito
    Para garantizar que cumplen con los requisitos legales y técnicos antes de recibir las muestras físicas

    Antecedentes:
        Dado que el investigador ha completado la carga de documentos oficiales y la matriz Darwin Core
        Y la solicitud se encuentra en el flujo de validación documental

    Escenario: Aprobación automatizada de solicitudes sin alertas
        Dado que la solicitud no presenta discrepancias de identidad
        Y la matriz no contiene alertas taxonómicas pendientes de revisión
        Cuando se realiza la evaluación integral de la solicitud
        Entonces la solicitud pasa a estado "Aprobada Documentalmente"
        Y se asigna un Código QR único para identificar el lote de muestras
        Y el Código QR queda disponible para el investigador
        Y el investigador es notificado de la habilitación para la entrega física

    Esquema del escenario: Revisión manual curatorial de solicitudes con alertas
        Dado que la solicitud ha sido retenida por una alerta de "<tipo_alerta>"
        Cuando el curador evalúa el caso y decide "<decision_curador>"
        Entonces la solicitud pasa a estado "<estado_solicitud>"
        Y como consecuencia "<consecuencia_curatorial>"

        Ejemplos:
            | tipo_alerta                    | decision_curador | estado_solicitud         | consecuencia_curatorial              |
            | Discrepancia de Identidad      | Aprobar          | Aprobada Documentalmente | Se emite el Código QR del lote       |
            | Especie no listada en catálogo | Aprobar          | Aprobada Documentalmente | Se emite el Código QR del lote       |
            | Discrepancia de Identidad      | Rechazar         | Requiere Corrección      | Se notifica el motivo del rechazo    |
            | Especie no listada en catálogo | Rechazar         | Requiere Corrección      | Se notifica el motivo del rechazo    |

    Escenario: Reasignación de prioridad para revisión manual
        Dado que una solicitud está en estado "Pendiente de Revisión por Curaduría"
        Cuando un curador clasifica la solicitud como "Prioritaria"
        Entonces la solicitud se posiciona al inicio de la cola de revisión de curaduría

    @donacion
    Escenario: Aprobación de Donación y transferencia de dominio
        Dado que una solicitud de "Donación" no presenta alertas taxonómicas
        Cuando se realiza la evaluación integral de la solicitud y su Carta de Cesión
        Entonces la solicitud pasa a estado "Aprobada Documentalmente"
        Y se genera el "Acta de Transferencia de Dominio"
        Y se asigna un Código QR único para identificar el lote
