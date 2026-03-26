Feature: Declaración de devolución de préstamo
    Como investigador,
    quiero informar la devolución de un préstamo,
    para dejar constancia de su entrega.

    Scenario: Informar la devolución de un préstamo
        Given que existe un préstamo activo asociado al investigador
        When el investigador registra la devolución del préstamo
        Then el préstamo queda marcado como pendiente de verificación de devolución
