# language: es
Característica: Monitoreo de tiempo de extracción de cajas
  Como curador responsable de la colección,
  quiero recibir alertas cuando una caja lleva demasiado tiempo fuera de su posición,
  para evitar el extravío del material biológico.

  Esquema del escenario: El sistema evalúa el tiempo que una caja lleva fuera de su posición
    Dado que existe una caja fuera de su posición con condición <condicion>
    Cuando se verifican los tiempos de extracción
    Entonces <resultado>

    Ejemplos:
      | condicion                          | resultado                                                                                               |
      | dentro del límite de tiempo        | el estado permanece "En Tránsito" sin alertas                                                            |
      | próxima a superar el límite        | se envía una notificación preventiva al curador responsable                                              |
      | superando el límite de 1 día hábil | se genera una alerta de "Extracción Prolongada" y el estado de la caja cambia a "Extracción Prolongada"  |

  Escenario: El sistema registra la devolución de una caja en extracción prolongada
    Dado que existe una caja en estado "Extracción Prolongada"
    Cuando el sistema detecta que la caja fue insertada en su ranura del gabinete
    Entonces la devolución se registra
    Y el estado de la caja vuelve a "En Gabinete"
