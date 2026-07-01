#language: es
Característica: Solicitud de prórroga de préstamo
    Como investigador con un préstamo activo
    Quiero solicitar una extensión del plazo de devolución
    Para continuar mi investigación dentro de los canales formales.

    Regla: La solicitud de prórroga debe realizarse antes del vencimiento del préstamo

        Escenario: Solicitar prórroga de un préstamo activo
            Dado que existe un préstamo activo del investigador para prórroga
            Cuando el investigador registra una solicitud de prórroga con una nueva fecha y justificación
            Entonces el préstamo queda en estado prórroga solicitada

        Escenario: No permitir solicitar prórroga sin justificación
            Dado que existe un préstamo activo del investigador para prórroga
            Cuando el investigador intenta registrar una solicitud de prórroga sin justificación
            Entonces el préstamo permanece en estado activo

        Escenario: No permitir solicitar prórroga con el préstamo vencido
            Dado que existe un préstamo en estado vencido asociado al investigador
            Cuando el investigador intenta registrar una solicitud de prórroga
            Entonces la solicitud no es permitida

        Esquema del escenario: Recibir notificación del resultado de la prórroga
            Dado que existe un préstamo en estado prórroga solicitada
            Cuando la prórroga es resuelta con resultado <resultado>
            Entonces el investigador recibe por correo la resolución de su prórroga <resultado>

            Ejemplos:
                | resultado |
                | aprobada  |
                | rechazada |
