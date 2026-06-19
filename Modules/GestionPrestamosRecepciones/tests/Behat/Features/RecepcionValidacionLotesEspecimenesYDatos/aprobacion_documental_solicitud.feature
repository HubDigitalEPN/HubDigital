# language: es
# Feature: 3
@listo
Característica: Aprobación documental de la solicitud
    Como curador
    Quiero revisar y decidir sobre las solicitudes de depósito y donación
    Para garantizar que cumplen con los requisitos legales y técnicos antes de recibir las muestras físicas

    Antecedentes:
        Dado que el investigador completó la carga de los documentos oficiales y de la matriz Darwin Core

    Escenario: El curador es notificado de una nueva solicitud por revisar
        Cuando el investigador envía la solicitud para revisión documental
        Entonces la solicitud pasa a estado "Pendiente de Revisión por Curaduría"
        Y se notifica al curador que hay una nueva solicitud por revisar

    Escenario: El curador aprueba una solicitud que llega sin alertas
        Dado que la solicitud está "Pendiente de Revisión por Curaduría"
        Y no presenta discrepancias de identidad ni alertas taxonómicas pendientes
        Cuando el curador confirma que la solicitud está validada
        Entonces la solicitud pasa a estado "Aprobada Documentalmente"
        Y la aprobación queda registrada en la auditoría con el curador responsable
        Y se asigna un Código QR único para identificar el lote de muestras
        Y el Código QR queda disponible para el investigador
        Y se notifica al investigador que ya puede descargar el Código QR para la entrega física

    Esquema del escenario: El curador aprueba una solicitud validando las alertas ya justificadas
        Dado que la solicitud está "Pendiente de Revisión por Curaduría"
        Y llega con la alerta "<tipo_alerta>" justificada por el investigador
        Cuando el curador acepta todas las justificaciones pendientes
        Entonces la solicitud pasa a estado "Aprobada Documentalmente"
        Y la aprobación queda registrada en la auditoría con el curador responsable
        Y se asigna un Código QR único para identificar el lote de muestras
        Y el Código QR queda disponible para el investigador
        Y se notifica al investigador que ya puede descargar el Código QR para la entrega física

        Ejemplos:
            | tipo_alerta                         |
            | Discrepancia de Identidad (Tercero) |
            | Especie no listada en catálogo      |
            | Hallazgo Taxonómico no Catalogado   |

    Escenario: El curador rechaza una solicitud porque no acepta una de sus justificaciones
        Dado que la solicitud está "Pendiente de Revisión por Curaduría"
        Y llega con varias alertas justificadas por el investigador
        Cuando el curador no acepta al menos una de las justificaciones
        Entonces la solicitud pasa a estado "Requiere Corrección"
        Y el comentario para el investigador indica cuáles justificaciones no fueron aceptadas
        Y se notifica al investigador el rechazo de su solicitud

    Esquema del escenario: El curador rechaza una solicitud justificando el motivo al investigador
        Dado que la solicitud está "Pendiente de Revisión por Curaduría"
        Cuando el curador la rechaza como "<tipo_rechazo>" indicando el motivo
        Entonces la solicitud pasa a estado "<estado_resultante>"
        Y el motivo del rechazo queda registrado como comentario para el investigador
        Y se notifica al investigador el rechazo de su solicitud

        Ejemplos:
            | tipo_rechazo | estado_resultante   |
            | Subsanable   | Requiere Corrección |
            | Definitivo   | Rechazo Permanente  |

    Escenario: Priorización de una solicitud en la cola de revisión curatorial
        Dado que la solicitud está en estado "Pendiente de Revisión por Curaduría"
        Cuando el curador la clasifica como "Prioritaria"
        Entonces la solicitud se posiciona al inicio de la cola de revisión de curaduría

    @donacion
    Escenario: El curador aprueba una donación con transferencia de dominio
        Dado que la solicitud de "Donación" está "Pendiente de Revisión por Curaduría"
        Y no presenta alertas taxonómicas ni discrepancias de identidad
        Cuando el curador confirma que la solicitud y su Carta de Cesión están validadas
        Entonces la solicitud pasa a estado "Aprobada Documentalmente"
        Y la aprobación queda registrada en la auditoría con el curador responsable
        Y se genera el "Acta de Transferencia de Dominio"
        Y se asigna un Código QR único para identificar el lote de muestras
        Y el Código QR queda disponible para el investigador
        Y se notifica al investigador que ya puede descargar el Código QR para la entrega física

    @donacion
    Esquema del escenario: El curador rechaza una donación cuya Carta de Cesión no corresponde
        Dado que la solicitud de "Donación" está "Pendiente de Revisión por Curaduría"
        Y el archivo adjunto como Carta de Cesión no corresponde a una carta de cesión válida
        Cuando el curador la rechaza como "<tipo_rechazo>" indicando el motivo
        Entonces la solicitud pasa a estado "<estado_resultante>"
        Y el motivo del rechazo queda registrado como comentario para el investigador
        Y se notifica al investigador el rechazo de su solicitud

        Ejemplos:
            | tipo_rechazo | estado_resultante   |
            | Subsanable   | Requiere Corrección |
            | Definitivo   | Rechazo Permanente  |
