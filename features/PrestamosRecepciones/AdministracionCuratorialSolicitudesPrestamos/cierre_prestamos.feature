# languague: es

Feature: Cierre de préstamos
    Como curador,
    quiero confirmar el cumplimiento del préstamo y comunicar su cierre,
    para dar por finalizado el proceso.

    Scenario: Confirmar devolución conforme de un préstamo
        Given que existe un préstamo en estado pendiente de verificación de devolución
        When el curador confirma que el préstamo fue devuelto conforme a lo establecido
        Then el préstamo cambia a estado finalizado

    Scenario: Registrar incidente en la devolución de un préstamo
        Given que existe un préstamo en estado pendiente de verificación de devolución
        When el curador registra un incidente en la devolución del préstamo
        Then el préstamo queda marcado como finalizado con incidente

    Scenario: Asociar incidente al cierre del préstamo
        Given que existe un préstamo con un incidente registrado
        When el curador confirma el cierre del préstamo
        Then el cierre del préstamo queda asociado al incidente registrado

    Scenario: No permitir cierre sin devolución registrada
        Given que existe un préstamo activo sin registro de devolución
        When el curador intenta cerrar el préstamo
        Then el préstamo permanece en curso
