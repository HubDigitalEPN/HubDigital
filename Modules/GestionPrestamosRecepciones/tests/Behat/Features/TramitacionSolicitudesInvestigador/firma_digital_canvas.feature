# language: es
Característica: Firma digital del acta de préstamo mediante canvas

  El investigador puede firmar digitalmente el acta de préstamo dibujando
  su firma en un canvas interactivo dentro de la plataforma, sin necesidad
  de descargar, firmar externamente y volver a subir el documento.

  Escenario: El investigador firma el acta digitalmente con una firma canvas válida
    Dado que existe un acta en estado pendiente de firma asignada al investigador
    Cuando el investigador envía su firma en formato PNG base64 válido
    Entonces el acta permanece en estado pendiente de firma
    Y la ruta de la firma digital queda registrada en el acta

  Escenario: No permitir firmar un acta que pertenece a otro investigador
    Dado que existe un acta en estado pendiente de firma asignada al investigador
    Cuando otro investigador intenta firmar digitalmente esa acta
    Entonces el sistema rechaza la operación por pertenencia

  Escenario: No permitir enviar una firma base64 con formato inválido
    Dado que existe un acta en estado pendiente de firma asignada al investigador
    Cuando el investigador envía un base64 con prefijo incorrecto
    Entonces el sistema rechaza la operación por firma inválida

  Escenario: No permitir enviar un base64 con contenido no decodificable
    Dado que existe un acta en estado pendiente de firma asignada al investigador
    Cuando el investigador envía un base64 con contenido corrupto
    Entonces el sistema rechaza la operación por firma inválida

  Esquema del escenario: No permitir firmar un acta que no está en estado pendiente de firma
    Dado que existe un acta en estado <estado_invalido> asignada al investigador
    Cuando el investigador intenta firmar digitalmente con una firma válida
    Entonces el sistema rechaza la operación por estado inválido

    Ejemplos:
      | estado_invalido      |
      | pendiente_envio      |
      | pendiente_validacion |
      | validada             |
