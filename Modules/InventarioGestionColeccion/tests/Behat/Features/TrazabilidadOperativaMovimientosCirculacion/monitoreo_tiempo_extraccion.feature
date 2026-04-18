#language: es
Característica: Monitoreo y alerta de tiempo de extracción
  Como curador responsable de la colección,
  quiero que se emita una notificación si una caja o espécimen ha estado fuera de su posición asignada por más de 1 día hábil (o el límite establecido),
  para evitar el extravío del material biológico.

  Esquema del escenario: Evaluación del tiempo de extracción de una caja
    Dado que existe una caja entomológica fuera de su posición con condición <condicion>
    Cuando se verifican los tiempos de extracción
    Entonces <resultado>

    Ejemplos:
      | condicion                          | resultado                                                                                                         |
      | dentro del límite de tiempo        | se debe registrar sin alertas y el estado permanece "En Tránsito"                                                 |
      | próxima a superar el límite        | se debe enviar una notificación preventiva al curador responsable                                                 |
      | superando el límite de 1 día hábil | se debe generar una alerta de "Tiempo de Extracción Excedido" y el estado cambia a "Extracción Prolongada"        |

  Escenario: Devolución de una caja que supera el límite de tiempo
    Dado que existe una caja entomológica en estado "Extracción Prolongada"
    Cuando el curador registra la devolución de la caja a su ranura en el gabinete
    Entonces se debe registrar la devolución
    Y el estado de la caja debe cambiar a "En Gabinete"
