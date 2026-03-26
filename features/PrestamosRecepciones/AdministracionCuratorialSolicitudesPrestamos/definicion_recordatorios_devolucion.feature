# languague: es

Feature: Definición de recordatorios de devolución
    Como curador,
    quiero definir cuándo y con qué frecuencia se notificará la devolución de los préstamos,
    para asegurar su retorno oportuno.

    Scenario: Definir recordatorios de devolución para un préstamo
        Given que existe un préstamo activo
        When el curador define el momento y la frecuencia de los recordatorios de devolución
        Then los recordatorios de vencimiento del prestamo queda configurado con los recordatorios definidos

    Scenario: Definir recordatorios para múltiples préstamos
        Given que existen múltiples préstamos activos
        When el curador define recordatorios de devolución para dichos préstamos
        Then cada préstamo queda configurado con los recordatorios definidos

    Scenario: Modificar configuración de recordatorios
        Given que existe un préstamo con recordatorios previamente definidos
        When el curador actualiza el momento o la frecuencia de los recordatorios
        Then el préstamo refleja la nueva configuración de recordatorios

