# Prompt: Refactorización de Infraestructura para Schema Personalizado (PostgreSQL)

**Contexto del Proyecto:**
Ya tenemos generada la Capa de Infraestructura (Migraciones y Modelos Eloquent) para nuestro módulo bajo Clean Architecture. He tomado la decisión de organizar los Bounded Contexts en la base de datos utilizando schemas personalizados en PostgreSQL (como en Supabase), en lugar de usar el schema `public` por defecto. El schema ya ha sido o será creado manualmente en la base de datos.

### Insumos proporcionados:
1. **[Nombre del Schema]:** Este es el schema de destino que debes utilizar para todas las tablas de este módulo.
2. **[Archivos de Infraestructura]:** Te proporcionaré el código actual de las Migraciones y los Modelos Eloquent anémicos que debes refactorizar.

---

### Tu Tarea:
Actúa como un **desarrollador experto en Laravel y PostgreSQL**. Debes modificar los archivos existentes de la capa de Infraestructura para asegurarte de que todas las tablas, relaciones y consultas apunten explícitamente al **[Nombre del Schema]** indicado, respetando el aislamiento estructural.

---

### Directrices Estrictas de Modificación:

#### 1. Modificación de las Migraciones (Tablas base):
* Abre todas las migraciones proporcionadas.
* En el método `up()`, debes anteponer el nombre del schema en el método `Schema::create`.
    * *Ejemplo:* `Schema::create('nombre_del_schema.nombre_de_la_tabla', function ...)`
* En el método `down()`, debes hacer exactamente lo mismo para garantizar que los rollbacks funcionen.
    * *Ejemplo:* `Schema::dropIfExists('nombre_del_schema.nombre_de_la_tabla');`

#### 2. Cuidado Crítico con las Claves Foráneas (Foreign Keys):
* Debido a que usamos UUIDs y un schema personalizado, **ESTÁ ESTRICTAMENTE PROHIBIDO usar el método `constrained()`** de Laravel. Si lo usas, Laravel podría intentar buscar la tabla foránea en el schema `public` y fallar.
* Debes definir las relaciones de forma manual y explícita, indicando el schema en el método `on()`.
    * *Ejemplo:*
      ```php
      $table->uuid('entidad_id');
      $table->foreign('entidad_id')
            ->references('id')
            ->on('nombre_del_schema.nombre_tabla_foranea')
            ->onDelete('cascade'); // O el comportamiento que aplique
      ```

#### 3. Modificación de los Modelos Eloquent (Anémicos):
* Abre todos los Modelos Eloquent proporcionados.
* Modifica la propiedad `$table` para que incluya obligatoriamente el prefijo del schema.
    * *Ejemplo:* `protected $table = 'nombre_del_schema.nombre_de_la_tabla';`
* Asegúrate de no alterar las propiedades de `$fillable`, `$primaryKey`, `$keyType` o `incrementing = false`.

---

### Entregable esperado:
Muéstrame el **código fuente completo y actualizado** de:
1. Todas las Migraciones corregidas.
2. Todos los Modelos Eloquent corregidos.

**ADVERTENCIA:** No modifiques nada de la lógica interna de los Repositorios, ni la configuración de los Value Objects, ni añadas métodos a los modelos. Enfócate exclusivamente en el enrutamiento de las tablas hacia el nuevo schema.

**¿Entendido? Espera a que te proporcione el [Nombre del Schema] y los [Archivos de Infraestructura] para comenzar.**
