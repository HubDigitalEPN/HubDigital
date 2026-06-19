#language: es
@listo
Característica: Resolución de solicitud de préstamo
    Como curador responsable de evaluar solicitudes
    Quiero registrar mi decisión sobre una solicitud
    Para definir su continuidad en el proceso.

    Escenario: Aprobar una solicitud de préstamo sin condiciones
        Dado que existe una solicitud de préstamo enviada para resolución
        Cuando el curador registra la aprobación de la solicitud con un tipo de préstamo y una duración establecida
        Entonces la solicitud queda en estado aprobada
        Y se genera el acta de préstamo con los datos de la solicitud

    Escenario: Aprobar una solicitud de préstamo con condiciones
        Dado que existe una solicitud de préstamo enviada para resolución
        Cuando el curador registra la aprobación de la solicitud con un tipo de préstamo, una duración establecida
        Y condiciones específicas para uno o más especímenes
        Entonces la solicitud queda en estado aprobada
        Y cada ítem queda asociado a sus condiciones específicas cuando aplican
        Y se genera el acta de préstamo

    Escenario: Observar una solicitud de préstamo
        Dado que existe una solicitud de préstamo enviada para resolución
        Cuando el curador registra la observación de la solicitud con un comentario
        Entonces la solicitud queda en estado observada

    Escenario: No permitir observar una solicitud sin comentario
        Dado que existe una solicitud de préstamo enviada para resolución
        Cuando el curador intenta registrar la observación de la solicitud sin comentario
        Entonces la solicitud se mantiene en estado enviada

    Escenario: Rechazar una solicitud de préstamo
        Dado que existe una solicitud de préstamo enviada para resolución
        Cuando el curador registra el rechazo de la solicitud con un comentario
        Entonces la solicitud queda en estado rechazada

    Escenario: No permitir rechazar una solicitud sin comentario
        Dado que existe una solicitud de préstamo enviada para resolución
        Cuando el curador intenta registrar el rechazo de la solicitud sin comentario
        Entonces la solicitud se mantiene en estado enviada

    Escenario: No permitir aprobar una solicitud sin tipo de préstamo
        Dado que existe una solicitud de préstamo enviada para resolución
        Cuando el curador intenta registrar la aprobación sin un tipo de préstamo
        Entonces la solicitud se mantiene en estado enviada

    Escenario: Aprobar una solicitud de préstamo con condiciones generales
        Dado que existe una solicitud de préstamo enviada para resolución
        Cuando el curador registra la aprobación con condiciones generales para el préstamo
        Entonces la solicitud queda en estado aprobada
        Y el acta registra las condiciones generales del préstamo
