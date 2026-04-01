#language: es
Característica: Resolución de solicitud de préstamo
    Como curador responsable de evaluar solicitudes
    Quiero registrar mi decisión sobre una solicitud
    Para definir su continuidad en el proceso.

    Escenario: Aprobar una solicitud de préstamo sin condiciones
        Dado que existe una solicitud en estado enviada
        Cuando el curador registra la aprobación de la solicitud
        Entonces la solicitud queda en estado aprobada
        Y se crea un préstamo en estado activo

    Escenario: Aprobar una solicitud de préstamo con condiciones
        Dado que existe una solicitud en estado enviada
        Cuando el curador registra la aprobación de la solicitud con condiciones
        Entonces la solicitud queda en estado aprobada
        Y la solicitud queda asociada a las condiciones establecidas
        Y se crea un préstamo en estado activo

    Escenario: Observar una solicitud de préstamo
        Dado que existe una solicitud en estado enviada
        Cuando el curador registra la observación de la solicitud con un comentario
        Entonces la solicitud queda en estado observada

    Escenario: Rechazar una solicitud de préstamo
        Dado que existe una solicitud en estado enviada
        Cuando el curador registra el rechazo de la solicitud con un comentario
        Entonces la solicitud queda en estado rechazada

    Escenario: No permitir rechazar una solicitud sin comentario
        Dado que existe una solicitud en estado enviada
        Cuando el curador intenta registrar el rechazo de la solicitud sin comentario
        Entonces la solicitud permanece en estado enviada
