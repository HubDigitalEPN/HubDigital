#language: es

Característica: Cierre de préstamos
    Como curador,
    quiero confirmar el cumplimiento del préstamo y comunicar su cierre,
    para dar por finalizado el proceso.

    Escenario: Confirmar devolución conforme de un préstamo
        Dado que existe un préstamo en estado pendiente de verificación de devolución
        Cuando el curador confirma que el préstamo fue devuelto conforme a lo establecido
        Entonces el préstamo cambia a estado finalizado

    Escenario: Registrar incidente en la devolución de un préstamo
        Dado que existe un préstamo en estado pendiente de verificación de devolución
        Cuando el curador registra un incidente en la devolución del préstamo
        Entonces el préstamo queda marcado como finalizado con incidente

    Escenario: Asociar incidente al cierre del préstamo
        Dado que existe un préstamo con un incidente registrado
        Cuando el curador confirma el cierre del préstamo
        Entonces el cierre del préstamo queda asociado al incidente registrado

    Escenario: No permitir cierre sin devolución registrada
        Dado que existe un préstamo activo sin registro de devolución
        Cuando el curador intenta cerrar el préstamo
        Entonces el préstamo permanece en curso
