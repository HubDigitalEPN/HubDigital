# behat:scaffold

Genera el archivo Context PHP desde un `.feature` y lo registra en `behat.php`.

## Uso

```bash
php artisan behat:scaffold <module> <capability> <feature>
```

## Argumentos

| Argumento    | Descripción                        | Ejemplo                              |
|--------------|------------------------------------|--------------------------------------|
| `module`     | Nombre del módulo                  | `GestionPrestamosRecepciones`        |
| `capability` | Carpeta del grupo de features      | `TramitacionSolicitudesInvestigador` |
| `feature`    | Nombre del `.feature` sin extensión | `envio_solicitud_prestamo`          |

## Ejemplo

```bash
php artisan behat:scaffold GestionPrestamosRecepciones TramitacionSolicitudesInvestigador envio_solicitud_prestamo
```

## Qué hace

1. Valida que el `.feature` existe en `Modules/<module>/tests/Behat/Features/<capability>/`
2. Parsea todos los steps (Dado/Cuando/Entonces) del feature
3. Omite steps que ya están definidos en otros contexts del mismo módulo (evita ambigüedad)
4. Genera el `Context.php` con métodos stub listos para implementar
5. Registra el context en `behat.php`
6. Formatea el archivo con Pint

## Cuándo usarlo

- Cada vez que creas un `.feature` nuevo y necesitas su context
- Si modificas un `.feature` (añades/cambias steps), vuelve a correrlo para sincronizar

> Si el context ya existe lo sobreescribe, así que es seguro correrlo varias veces.

## Después del scaffold

Corre la feature para ver qué steps están pendientes de implementar:

```bash
php vendor/bin/behat --config=behat.php --suite=<module> <ruta-al-feature>
```

### Estados posibles en la salida

| Estado      | Significado |
|-------------|-------------|
| `pending`   | Step definido pero sin implementar (`throw new PendingException`) |
| `skipped`   | No se ejecutó porque un step anterior está pendiente o falló |
| `undefined` | Sin ninguna definición — no debería ocurrir tras el scaffold |
| `passed`    | Step implementado y funcionando correctamente |
