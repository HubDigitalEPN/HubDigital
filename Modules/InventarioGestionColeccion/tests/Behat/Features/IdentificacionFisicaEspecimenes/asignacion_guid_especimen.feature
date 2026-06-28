#language: es
@listo
Característica: Asignación de GUID único al registrar un espécimen
  Como curador responsable de la colección,
  quiero que cada espécimen registrado reciba un GUID único automáticamente,
  para garantizar su identificación unívoca en el sistema.

  Escenario: Asignar GUID al registrar un nuevo espécimen
    Dado que el curador tiene los datos completos de un nuevo espécimen
    Cuando el curador registra el espécimen en el sistema
    Entonces el espécimen queda registrado con un GUID único asignado

  Escenario: Dos especímenes no pueden compartir el mismo GUID
    Dado que existe un espécimen registrado con su GUID asignado
    Cuando el sistema intenta registrar un segundo espécimen con el mismo GUID
    Entonces el sistema rechaza el registro indicando GUID duplicado
