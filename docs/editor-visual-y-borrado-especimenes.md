# Edición editorial y borrado de especímenes

La única entrada de administración editorial es **Contenidos y textos**, en
`/administracion/contenidos`, dentro de **Administración**, junto con **Asistente
del portal**. Sus secciones conservan los permisos existentes:
portales y fichas para curaduría; Laboratorio para Curador/Administrador; textos
del formulario de depósitos y textos generales para Administrador. Las antiguas
pantallas `/administracion/el-laboratorio` y
`/administracion/fichas-taxonomicas` se retiran sin redirecciones.

## Cobertura editorial

| Área pública | Administración | Persistencia |
| --- | --- | --- |
| Inicio, cabecera, navegación, pie y contacto | Selección sobre la página real y lista completa de campos, incluidos atributos accesibles | `usuarios.textos_portal` |
| Portal de depósitos | Selección visual y campos del grupo Depósitos | `usuarios.textos_portal` |
| Colección, explicaciones de indicadores y pies de figuras | Página real y campos de cada sección | `usuarios.textos_portal` |
| Privacidad, términos, licencia, citas y créditos | Página real; los párrafos con enlaces identifican su campo sin perder los marcadores | `usuarios.textos_portal` |
| El Laboratorio, perfiles y bloques propios | Composición visual, doble clic en textos, fotografías y posiciones | JSON `pagina_laboratorio.contenido` en `usuarios.textos_portal` |
| Fichas del explorador | Sección Fichas dentro de la misma entrada | Tabla PostgreSQL de fichas taxonómicas existente |
| Instrucciones del formulario de depósitos | Sección Formulario de depósitos | `usuarios.textos_wizard` |
| Etiquetas, botones, ayudas, atributos y otros literales de interfaz | Selección visual de literales inventariados; sección Textos generales con búsqueda y alta de un texto adicional | `usuarios.textos_sistema` y overrides editoriales por página |

El inventario de literales examina las fuentes propias; excluye dependencias y
compilados. Los campos canónicos tienen una lista independiente del contenido
visible, por lo que también pueden editarse textos de ventanas y accesibilidad.
Un texto repetido exige elegir el campo, en lugar de cambiar arbitrariamente su
primera coincidencia. Los datos científicos, identidades de releases, permisos,
documentos firmados e historial no se tratan como contenido editorial.

La comprobación de cobertura debe registrar los grupos, rutas y campos realmente
verificados. La existencia del inventario por sí sola no acredita haber probado
cada texto dinámico o cada estado de la aplicación.

El editor del formulario muestra las siete pantallas originales del wizard con
datos de ejemplo y controles inertes. Seleccionar un texto abre su ubicación;
guardar modifica la configuración PostgreSQL sin crear ni retomar expedientes.
Los textos generales identifican pantalla y campo, y explican el alcance global
de cada sustitución exacta. Sus buscadores y listas conservan el patrón APEX.

El modal del portal abre en el cliente al seleccionar un texto. Mientras se lee
su versión de PostgreSQL muestra el estado de carga y bloquea la publicación;
la selección no vuelve a renderizar la página completa. La maqueta de depósitos
elimina los indicadores de carga y las restricciones de los campos de ejemplo.
Las reglas del trámite operativo conservan su funcionamiento.

«Reorganizar posiciones» muestra asas para arrastrar objetos dentro de su bloque;
las flechas sobre un asa cambian su orden con teclado. «Guardar posiciones» publica
el orden en PostgreSQL por página o paso, con control de versión. El mismo orden
se aplica al portal o formulario operativo y a su recorrido por teclado. Se conserva
el ancho adaptable de cada campo. «Restaurar posiciones» recupera el orden original.
El Laboratorio mantiene su editor de composición libre, tamaños y capas.
Las claves `disposicion.portal.*` y `disposicion.wizard.*` de `usuarios.textos_portal`
contienen exclusivamente identidades y orden, sin HTML ni valores del expediente.

Las vistas previas tienen altura limitada por el navegador y desplazamiento propio.
Fichas dispone de una tabla izquierda más ancha, acción de edición visible y carga
continua al desplazar; el panel derecho explica qué se publica en el explorador.

## Grids continuos

Las tablas interactivas comparten el menú del encabezado para ordenar, filtrar,
mover u ocultar columnas. El menú indica si el filtro abarca la consulta completa
o las filas cargadas. La rueda, el tacto y las teclas de desplazamiento cargan
las siguientes filas dentro de una altura limitada por el navegador. En consultas
paginadas se conserva una ventana de hasta tres páginas y 600 filas, con lecturas
frescas; al retroceder se recuperan las páginas anteriores. Los documentos y
correos conservan su formato. La paginación sigue disponible como acceso directo.

## Composición de El Laboratorio

Se reutiliza el motor de composición existente. El administrador puede mover,
redimensionar y ordenar capas de textos y fotos; las flechas permiten un ajuste
preciso. Doble clic abre el texto o bloque seleccionado. Publicar guarda textos,
bloques y geometría en PostgreSQL con control de versión. Una edición concurrente
no sobrescribe silenciosamente la de otra persona. En móviles se conserva el
orden vertical de lectura. Las fotografías mantienen su almacenamiento vigente.

## Borrado definitivo

El catálogo permite borrar una fila o una selección después de mostrar sus
códigos en una confirmación explícita. No hay papelera ni copia recuperable del
espécimen; no se bloquea por publicación, préstamo o estado. El permiso
`inventario.eliminar` se exige también en el servidor y se asigna inicialmente a
Administrador y Curador.

La transacción bloquea los UUID confirmados, registra únicamente UUID, código,
autor y fecha en `taxonomia.eliminaciones_especimenes`, retira vínculos dependientes
y borra las filas. Los préstamos conservan código e instantánea histórica; la
referencia al espécimen queda nula. Los expedientes de depósito, sus documentos,
contenido recibido e historial se conservan. Un fallo de integridad revierte todo
el lote. La revisión de caché pública se actualiza por los triggers existentes.

Las pruebas de eliminación de QA en producción se seleccionan por UUID, código y
marca QA previamente comprobados, nunca únicamente por fecha.
