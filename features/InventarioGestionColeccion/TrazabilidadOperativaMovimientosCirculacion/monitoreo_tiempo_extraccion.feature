# language: es
Característica: Monitoreo y alerta de tiempo de extracción
  Como Curador,
  quiero que se emita una notificación si una caja o espécimen ha estado fuera de su posición asignada por más de 1 día hábil (o el límite establecido),
  para evitar el extravío del material biológico.

  Escenario: Caja devuelta antes del límite establecido
    Dado que el límite de tiempo de extracción para una caja es de "1 día hábil"
    Y la caja entomológica "CAJA-500" fue retirada hace "4 horas"
    Cuando devuelvo la caja "CAJA-500" a su ranura en el gabinete
    Entonces se debe registrar la devolución

  Escenario: Caja no devuelta superando el límite de 1 día hábil
    Dado que el límite de tiempo de extracción para una caja es de "1 día hábil"
    Y la caja entomológica "CAJA-500" fue retirada hace "1 día y 2 horas"
    Cuando se verifican los tiempos de extracción
    Entonces se debe generar una alerta de "Tiempo de Extracción Excedido"
    Y se debe enviar una notificación inmediata al curador responsable de la custodia
    Y el estado de la caja debe actualizarse a "Extracción Prolongada"
