#language: es
Característica: Cierre de préstamo por devolución
    Como investigador con un préstamo activo
    Quiero devolver los especímenes prestados
    Para cerrar formalmente el préstamo.

    Escenario: Declarar la devolución de un préstamo
        Dado que existe un préstamo activo asociado al investigador
        Cuando el investigador registra la devolución del préstamo
        Entonces el préstamo queda en estado pendiente de verificación

    Esquema del escenario: Recibir notificación de cierre del préstamo
        Dado que existe un préstamo del investigador en estado pendiente de verificación
        Cuando la devolución del préstamo es verificada con resultado <resultado>
        Entonces el investigador recibe una notificación por correo informando el cierre del préstamo con <resultado>

        Ejemplos:
            | resultado        |
            | sin novedades    |
            | con observación  |
