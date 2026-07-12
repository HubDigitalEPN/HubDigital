<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Puerto de la capa de aplicación para resolver el nombre legible de un usuario
 * a partir de su identificador.
 *
 * Lo implementa un adaptador en Infrastructure que consulta el modelo de usuarios.
 */
interface UsuarioNombrePort
{
    /** Retorna el nombre del usuario indicado, o null si no existe. */
    public function obtenerNombre(string $usuarioId): ?string;

    /**
     * Retorna los datos profesionales (nombre, cargo, institución) del usuario
     * indicado para el Acta recepción-depósito, o null si no existe.
     */
    public function obtenerDatosDepositante(string $usuarioId): ?DatosDepositante;

    /**
     * Retorna un mapa identificador→nombre para los usuarios indicados.
     *
     * @param  array<int, string>  $usuarioIds
     * @return array<string, string>
     */
    public function obtenerNombres(array $usuarioIds): array;

    /**
     * Retorna los identificadores de los usuarios cuyo nombre coincide con el término.
     *
     * @return array<int, string>
     */
    public function buscarIdsPorNombre(string $termino): array;
}
