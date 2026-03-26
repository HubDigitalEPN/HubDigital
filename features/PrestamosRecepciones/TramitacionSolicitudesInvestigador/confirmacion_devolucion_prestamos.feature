# languague: es

Feature: Confirmación de devolución de préstamos
    Como investigador,
    quiero tener confirmación del estado final de mis devoluciones,
    para asegurar el cierre de mis préstamos.

    Scenario: Confirmar que un préstamo declarado ha sido verificado
        Given que existe un préstamo asociado al investigador en estado pendiente de verificación
        When el curador verifica y registra la devolución
        Then el investigador puede constatar que el préstamo se encuentra devuelto

    Scenario: Confirmar el cierre final de un préstamo
        Given que un préstamo del investigador ha sido devuelto y verificado
        When el investigador consulta el préstamo
        Then puede constatar que el préstamo se encuentra cerrado

    Scenario: Consultar un préstamo no devuelto
        Given que existe un préstamo activo asociado al investigador sin registro de devolución
        When el investigador consulta el préstamo
        Then puede constatar que el préstamo no se encuentra cerrado
