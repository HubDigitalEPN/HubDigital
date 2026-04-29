# language: es
Característica: Registro automático de ubicación de cajas
  Como curador responsable de la colección,
  quiero que se registre automáticamente cuando una caja entomológica es ingresada o retirada de una ranura del gabinete,
  para detectar movimientos no autorizados en tiempo real y evitar las pérdidas de material biológico.

  Esquema del escenario: Registro de movimiento de una caja en una ranura del gabinete
    Dado que se está monitoreando una ranura del gabinete
    Y existe una caja entomológica en condición de <condicion_previa>
    Cuando <accion> la caja entomológica en la ranura
    Entonces se debe registrar el evento de <accion>
    Y el estado de la caja debe cambiar a <estado_resultante>

    Ejemplos:
      | condicion_previa          | accion  | estado_resultante |
      | ranura vacía disponible   | ingreso | En Gabinete       |
      | registrada en una ranura  | retiro  | En Tránsito       |

  Escenario: Movimiento no autorizado fuera del horario establecido
    Dado que el horario autorizado de movimiento de ranuras está configurado
    Y la hora actual está fuera del horario autorizado
    Cuando retiro una caja entomológica de su ranura
    Entonces se debe registrar el evento de retiro
    Y se debe generar una alerta de "Movimiento No Autorizado"
    Y el curador responsable debe recibir una notificación inmediata
