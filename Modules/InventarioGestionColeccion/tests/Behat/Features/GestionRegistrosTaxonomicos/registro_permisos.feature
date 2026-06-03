# language: es
Característica: Registro de permisos de investigación, transporte y exportación
  Como curador responsable de la colección,
  quiero registrar permisos asociados a actividades de la colección,
  para enlazarlos posteriormente a los especímenes que cubren.

  Escenario: El curador registra un permiso de investigación
    Dado que el curador tiene los datos de un permiso de investigación
    Cuando el curador registra el permiso
    Entonces el permiso queda registrado en el sistema

  Escenario: El curador no puede registrar un permiso con tipo inválido
    Dado que el curador tiene un permiso con tipo desconocido
    Cuando el curador registra el permiso
    Entonces el sistema rechaza el registro indicando tipo inválido

  Escenario: El curador lista los permisos registrados
    Dado que existen varios permisos registrados de distintos tipos
    Cuando el curador lista los permisos
    Entonces se muestran todos los permisos con su tipo y responsable
