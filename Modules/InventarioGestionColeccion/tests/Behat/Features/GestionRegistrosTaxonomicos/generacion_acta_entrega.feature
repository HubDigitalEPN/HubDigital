#language: es
Característica: Generación automatizada del acta de entrega
  Como curador responsable de la colección,
  quiero generar automáticamente el documento Acta de Entrega,
  para formalizar la recepción de especímenes de un depositante.

  Escenario: Generar acta de entrega para una entidad con especímenes
    Dado que existe una entidad depositante con especímenes registrados en estado disponible
    Cuando el curador solicita la generación del acta de entrega
    Entonces el acta de entrega es generada y queda disponible para descarga

  Escenario: No se puede generar acta si la entidad no tiene especímenes vinculados
    Dado que existe una entidad depositante sin especímenes registrados
    Cuando el curador solicita la generación del acta de entrega
    Entonces el sistema rechaza la generación indicando que no hay especímenes
