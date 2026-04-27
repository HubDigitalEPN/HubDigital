#language: es
Característica: Validación automática según el estándar Darwin Core
  Como curador responsable de la calidad de los datos,
  quiero que el sistema valide los campos obligatorios de cada espécimen según Darwin Core,
  para garantizar la interoperabilidad con sistemas científicos internacionales.

  Esquema del escenario: Validación de campo obligatorio Darwin Core ausente
    Dado que existe un espécimen con el campo "<campo>" vacío
    Cuando se ejecuta la validación Darwin Core sobre el espécimen
    Entonces el sistema genera un error de validación indicando que "<campo>" es obligatorio

    Ejemplos:
      | campo           |
      | codigo_catalogo |
      | taxon_id        |
      | localidad       |
      | fecha_colecta   |
      | colector        |

  Escenario: Espécimen con todos los campos obligatorios pasa la validación
    Dado que existe un espécimen con todos los campos obligatorios Darwin Core completos
    Cuando se ejecuta la validación Darwin Core sobre el espécimen
    Entonces el espécimen pasa la validación sin errores
