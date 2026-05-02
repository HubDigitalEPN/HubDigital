#language: es
Característica: Verificación de consistencia entre documentos físicos y el sistema
  Como curador responsable de la colección,
  quiero verificar que los documentos físicos sean consistentes con los registros del sistema,
  para asegurar la integridad legal de la colección.

  Escenario: Documentos consistentes con el sistema no generan alertas
    Dado que existe un espécimen con documentos registrados y datos consistentes en el sistema
    Cuando se ejecuta la verificación de consistencia documental
    Entonces la verificación concluye sin inconsistencias

  Escenario: Documentos inconsistentes con el sistema generan una alerta
    Dado que existe un espécimen con datos que no coinciden con sus documentos físicos
    Cuando se ejecuta la verificación de consistencia documental
    Entonces el sistema genera una alerta de inconsistencia documental
