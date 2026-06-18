# language: es
@listo
Característica: Alerta de orden taxonómico en la disposición de cajas
  Como curador responsable de la colección,
  quiero que el sistema me alerte si una caja queda fuera del orden taxonómico esperado en el gabinete,
  para mantener la colección organizada alfabéticamente por subfamilia y género sin bloquear el flujo de trabajo.

  Esquema del escenario: El sistema evalúa si la caja respeta el orden taxonómico al ser insertada en el gabinete
    Dado que el gabinete tiene cajas vecinas organizadas en orden taxonómico
    Y la caja a insertar tiene una clasificación <posicion> respecto a las cajas adyacentes
    Cuando el sistema detecta que la caja fue insertada en el gabinete
    Entonces <resultado>

    Ejemplos:
      | posicion                              | resultado                                                                                    |
      | en orden taxonómico correcto          | el ingreso se registra exitosamente sin alertas                                              |
      | fuera del orden alfabético por subfamilia | el ingreso se registra y se genera una alerta suave de "Orden Taxonómico Fuera de Secuencia" |
      | fuera del orden alfabético por género | el ingreso se registra y se genera una alerta suave de "Orden Taxonómico Fuera de Secuencia" |

  Escenario: El sistema registra con alerta el ingreso de una caja sin unit trays asignados
    Dado que existe una caja sin unit trays asignados
    Cuando el sistema detecta que la caja fue insertada en el gabinete
    Entonces el ingreso se registra en el gabinete
    Y se genera una alerta de "Familia No Asignada"
    Y la caja queda en estado "Pendiente de Clasificación"

  Escenario: El sistema registra sin alerta el ingreso de una caja especial con observación
    Dado que existe una caja marcada como "Caja Especial" con la observación "Especímenes incautados - origen no determinado"
    Cuando el sistema detecta que la caja fue insertada en el gabinete
    Entonces el ingreso se registra con su observación
    Y no se genera ninguna alerta de orden taxonómico
