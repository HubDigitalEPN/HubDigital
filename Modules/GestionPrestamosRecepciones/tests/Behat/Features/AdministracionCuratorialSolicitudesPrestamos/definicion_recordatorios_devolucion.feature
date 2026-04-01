#language: es
Característica: Definición de recordatorios de devolución
    Como curador responsable del proceso de préstamo
    Quiero configurar los recordatorios de devolución
    Para asegurar el retorno oportuno de los especímenes.

    Escenario: Definir configuración global de recordatorios
        Dado que no existe una configuración global de recordatorios definida
        Cuando el curador registra la configuración global de recordatorios
        Entonces todos los préstamos quedan sujetos a la configuración definida

    Escenario: Modificar configuración global de recordatorios
        Dado que existe una configuración global de recordatorios previamente definida
        Cuando el curador actualiza la configuración global de recordatorios
        Entonces todos los préstamos quedan sujetos a la configuración actualizada

    Escenario: Modificar recordatorios de un préstamo específico
        Dado que existe un préstamo activo con recordatorios configurados
        Cuando el curador actualiza la configuración de recordatorios del préstamo
        Entonces el préstamo queda configurado con los recordatorios actualizados

    Escenario: Generar recordatorios automáticamente al aprobar una solicitud
        Dado que existe una solicitud en estado enviada
        Y existe una configuración global de recordatorios definida
        Cuando el curador registra la aprobación de la solicitud
        Entonces se generan los recordatorios de devolución según la configuración global
