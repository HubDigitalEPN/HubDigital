#language: es
Característica: Cierre de préstamo por devolución
    Como investigador con un préstamo activo
    Quiero notificar que he enviado los especímenes de vuelta
    Para informar que el material está en camino.

    Escenario: Declarar la devolución de un préstamo
        Dado que existe un préstamo activo asociado al investigador
        Cuando el investigador registra el envío de devolución del préstamo
        Entonces el préstamo pasa a estado en revisión

    Esquema del escenario: Recibir notificación del resultado de la verificación
        Dado que existe un préstamo del investigador en estado en revisión
        Cuando la devolución es verificada con resultado <resultado>
        Entonces el investigador recibe una notificación por correo con el resultado <resultado>

        Ejemplos:
            | resultado       |
            | sin novedades   |
            | con observación |

