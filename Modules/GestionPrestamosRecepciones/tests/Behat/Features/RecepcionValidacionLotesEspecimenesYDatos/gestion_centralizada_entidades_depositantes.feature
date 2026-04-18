# language: es
Característica: Gestión centralizada de entidades depositantes
    Como investigador
    Quiero registrar mi institución en un catálogo
    Para realizar el depósito de especímenes entomológicos y agilizar la recepción de futuros lotes

    Escenario: Registrar exitosamente una nueva institución que no existe en el catálogo
        Dado que no existe una institución con el nombre "Instituto de Biodiversidad"
        Cuando intento registrar una nueva institución con los datos:
            | campo                                          | valor                      |
            | Nombre de Empresa / Institución                | Instituto de Biodiversidad |
            | Nombre del Representante Legal                 | Juan Pérez                 |
            | Cargo o Posición                               | Director                   |
            | Correo Electrónico                             | contacto@instituto.org     |
            | Contraseña                                     | Seguro123*                 |
        Entonces la institución debe ser registrada exitosamente
        Y debo ver un mensaje de confirmación "Institución registrada correctamente"
        Y podré usar la institución para realizar depósitos de lotes

    Escenario: No puedo registrar una institución que ya existe en el catálogo
        Dado que existe una institución registrada con el nombre "Instituto Nacional de Biodiversidad"
        Cuando intento registrar una nueva institución con el mismo nombre "Instituto Nacional de Biodiversidad"
        Entonces debo ver un error "Esta institución ya se encuentra registrada en el catálogo"
        Y la institución no debe ser registrada

    Esquema del escenario: Validar campos requeridos al registrar una institución
        Dado que intento registrar una institución sin completar los campos obligatorios
        Cuando envío el formulario sin llenar el campo <campo_faltante>
        Entonces debo ver un error "<mensaje_error>"
        Y la institución no debe ser registrada

        Ejemplos:
            | campo_faltante                                 | mensaje_error                                                |
            | Nombre de Empresa / Institución                | El nombre de la empresa / institución es requerido           |
            | Nombre del Representante Legal                 | El nombre del representante legal es requerido               |
            | Cargo o Posición                               | El cargo o posición es requerido                             |
            | Correo Electrónico                             | El correo electrónico es requerido                           |
            | Contraseña                                     | La contraseña es requerida                                   |
