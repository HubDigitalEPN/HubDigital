#language: es
Característica: Tramitacion de solicitud de préstamo
    Como investigador que desea obtener especímenes en préstamo
    Quiero gestionar mi solicitud hasta su formalización
    Para iniciar formalmente el proceso de préstamo.

    Escenario: Guardar una solicitud como borrador
        Dado que el investigador ha ingresado información en una solicitud
        Cuando el investigador registra la solicitud
        Entonces la solicitud queda registrada en estado borrador

    Escenario: Editar una solicitud en estado borrador
        Dado que existe una solicitud en estado borrador
        Y el investigador tiene acceso a dicha solicitud
        Cuando el investigador actualiza la información de la solicitud
        Entonces la solicitud refleja la información actualizada
        Y la solicitud permanece en estado borrador

    Esquema del escenario: Enviar una solicitud con información completa
        Dado que existe una solicitud en estado <estado_previo> con su información requerida completa
        Cuando el investigador envía la solicitud
        Entonces la solicitud queda en estado enviada

        Ejemplos:
            | estado_previo |
            | borrador      |
            | observada     |

    Esquema del escenario: No permitir enviar una solicitud con información incompleta
        Dado que existe una solicitud en estado <estado_previo> con información incompleta
        Cuando el investigador envía la solicitud
        Entonces la solicitud permanece en estado <estado_previo>

        Ejemplos:
            | estado_previo |
            | borrador      |
            | observada     |

    Escenario: Firmar y enviar el acta de préstamo
        Dado que el investigador ha recibido el acta de préstamo
        Cuando el investigador sube el acta firmada
        Entonces el acta queda en estado pendiente de validación

    Escenario: Recibir notificación de devolución del acta por firma inválida
        Dado que el investigador ha subido el acta firmada
        Cuando el curador devuelve el acta por motivos de firma
        Entonces el investigador recibe una notificación con el motivo de la devolución
