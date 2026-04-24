#language: es
Característica: Gestión de prórrogas de préstamo
    Como curador responsable del proceso de préstamo
    Quiero resolver las solicitudes de prórroga
    Para controlar la extensión de los plazos para prestamos.

    Escenario: Aprobar una solicitud de prórroga
        Dado que existe un préstamo en estado prórroga solicitada
        Cuando el curador registra la aprobación de la prórroga con una nueva fecha de vencimiento
        Entonces el préstamo queda en estado activo con la nueva fecha de vencimiento

    Escenario: Rechazar una solicitud de prórroga
        Dado que existe un préstamo en estado prórroga solicitada
        Cuando el curador registra el rechazo de la prórroga con un comentario
        Entonces el préstamo queda en estado activo con la fecha de vencimiento original

    Escenario: No permitir rechazar una prórroga sin comentario
        Dado que existe un préstamo en estado prórroga solicitada
        Cuando el curador intenta registrar el rechazo de la prórroga sin comentario
        Entonces el préstamo permanece en estado prórroga solicitada
