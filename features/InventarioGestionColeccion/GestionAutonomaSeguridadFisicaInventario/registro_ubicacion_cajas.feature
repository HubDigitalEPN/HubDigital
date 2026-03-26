# language: es
Característica: Registro automático de ubicación de cajas
  Como Curador,
  quiero que se registre automáticamente cuando una caja entomológica es ingresada o retirada de una ranura del gabinete,
  para detectar movimientos no autorizados en tiempo real y evitar las pérdidas de material biológico.
  
  Escenario: Ingreso de una caja entomológica en una ranura vacía
    Dado que se está monitoreando el gabinete "GAB-01"
    Y la ranura "A-1" se encuentra vacía
    Cuando ingreso la caja entomológica "CAJA-100" en la ranura "A-1"
    Entonces se debe registrar automáticamente la ubicación "A-1" para la caja "CAJA-100"
    Y el estado de la caja debe cambiar a "En Gabinete"

  Escenario: Retiro de una caja entomológica de una ranura
    Dado que se está monitoreando el gabinete "GAB-01"
    Y la caja entomológica "CAJA-100" está registrada en la ranura "A-1"
    Cuando retiro la caja entomológica "CAJA-100" de la ranura "A-1"
    Entonces se debe registrar el evento de retiro de la caja "CAJA-100"
    Y el estado de la caja debe cambiar a "En Tránsito"
    Y la ranura "A-1" debe quedar marcada como vacía

  Escenario: Movimiento no autorizado fuera del horario establecido
    Dado que el horario autorizado de movimiento es de "08:00" a "18:00"
    Y son las "20:00"
    Cuando retiro la caja entomológica "CAJA-100" de la ranura "A-1"
    Entonces se debe registrar el evento de retiro
