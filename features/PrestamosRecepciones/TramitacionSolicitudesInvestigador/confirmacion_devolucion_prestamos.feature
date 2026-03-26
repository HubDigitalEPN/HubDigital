#language: es

Característica: Confirmación de devolución de préstamos
    Como investigador,
    quiero tener confirmación del estado final de mis devoluciones,
    para asegurar el cierre de mis préstamos.

    Escenario: Confirmar que un préstamo declarado ha sido verificado
        Dado que existe un préstamo asociado al investigador en estado pendiente de verificación
        Cuando el curador verifica y registra la devolución
        Entonces el investigador puede constatar que el préstamo se encuentra devuelto

    Escenario: Confirmar el cierre final de un préstamo
        Dado que un préstamo del investigador ha sido devuelto y verificado
        Cuando el investigador consulta el préstamo
        Entonces puede constatar que el préstamo se encuentra cerrado

    Escenario: Consultar un préstamo no devuelto
        Dado que existe un préstamo activo asociado al investigador sin registro de devolución
        Cuando el investigador consulta el préstamo
        Entonces puede constatar que el préstamo no se encuentra cerrado
