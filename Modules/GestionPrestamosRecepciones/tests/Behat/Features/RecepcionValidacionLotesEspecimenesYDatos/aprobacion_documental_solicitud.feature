# language: es
Característica: Aprobación documental de la solicitud
    Como curador
    Quiero revisar y decidir sobre las solicitudes de depósito
    Para garantizar que cumplen con los requisitos legales y técnicos antes de recibir las muestras físicas

    Antecedentes:
        Dado que el investigador ha completado la carga de documentos oficiales y la matriz Darwin Core
        Y la solicitud se encuentra en el flujo de validación documental

    Escenario: Aprobación automatizada de solicitudes sin alertas (Fast-Track)
        Dado que la solicitud no presenta discrepancias de identidad
        Y la matriz no contiene alertas taxonómicas pendientes de revisión
        Cuando el sistema procesa la validación integral de la solicitud
        Entonces el estado de la solicitud cambia a "Aprobada Documentalmente"
        Y el sistema genera un Código QR único para el lote de muestras
        Y el investigador puede descargar el Código QR en formato PDF (impreso o digital)
        Y el sistema notifica al investigador que está habilitado para la entrega física

    Esquema del escenario: Revisión manual curatorial de solicitudes con alertas
        Dado que la solicitud ha sido retenida por la alerta de "<tipo_alerta>"
        Cuando el curador revisa la solicitud y toma la decisión de "<decision_curador>"
        Entonces el estado de la solicitud cambia a "<estado_solicitud>"
        Y el sistema ejecuta la acción correspondiente de "<accion_sistema>"

        Ejemplos:
            | tipo_alerta                    | decision_curador | estado_solicitud         | accion_sistema               |
            | Discrepancia de Identidad      | Aprobar          | Aprobada Documentalmente | Generar etiquetas QR         |
            | Especie no listada en catálogo | Aprobar          | Aprobada Documentalmente | Generar etiquetas QR         |
            | Discrepancia de Identidad      | Rechazar         | Requiere Corrección      | Notificar motivo del rechazo |
            | Especie no listada en catálogo | Rechazar         | Requiere Corrección      | Notificar motivo del rechazo |

    Escenario: Reasignación de prioridad para revisión manual
        Dado que una solicitud está en estado "Pendiente de Revisión por Curaduría"
        Cuando un curador marca la solicitud como "Prioritaria"
        Entonces la solicitud se posiciona al inicio de la cola de revisión
        Y se resalta visualmente en el panel de control del equipo de curaduría
