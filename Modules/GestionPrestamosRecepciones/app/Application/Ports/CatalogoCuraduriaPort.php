<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

// TODO: Implementar adapter real cuando el módulo InventarioGestionColeccion
//       exponga la configuración de campos DwC obligatorios por colección.
//       El adapter real consultará ese módulo a través de su API/servicio público.
interface CatalogoCuraduriaPort
{
    /**
     * Devuelve los nombres de campos Darwin Core que la colección exige como obligatorios.
     *
     * @param  string  $coleccionId  Identificador de la colección destino
     * @return string[] Lista de nombres de campos DwC requeridos (ej: ['decimalLatitude', 'habitat'])
     */
    public function camposRequeridos(string $coleccionId): array;
}
