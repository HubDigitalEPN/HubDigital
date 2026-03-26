#language: es

Característica: Declaración de devolución de préstamo
    Como investigador,
    quiero informar la devolución de un préstamo,
    para dejar constancia de su entrega.

    Escenario: Informar la devolución de un préstamo
        Dado que existe un préstamo activo asociado al investigador
        Cuando el investigador registra la devolución del préstamo
        Entonces el préstamo queda marcado como pendiente de verificación de devolución
