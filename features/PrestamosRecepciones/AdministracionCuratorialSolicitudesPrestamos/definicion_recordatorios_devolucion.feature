#language: es

Característica: Definición de recordatorios de devolución
    Como curador,
    quiero definir cuándo y con qué frecuencia se notificará la devolución de los préstamos,
    para asegurar su retorno oportuno.

    Escenario: Definir recordatorios de devolución para un préstamo
        Dado que existe un préstamo activo
        Cuando el curador define el momento y la frecuencia de los recordatorios de devolución
        Entonces los recordatorios de vencimiento del prestamo queda configurado con los recordatorios definidos

    Escenario: Definir recordatorios para múltiples préstamos
        Dado que existen múltiples préstamos activos
        Cuando el curador define recordatorios de devolución para dichos préstamos
        Entonces cada préstamo queda configurado con los recordatorios definidos

    Escenario: Modificar configuración de recordatorios
        Dado que existe un préstamo con recordatorios previamente definidos
        Cuando el curador actualiza el momento o la frecuencia de los recordatorios
        Entonces el préstamo refleja la nueva configuración de recordatorios

