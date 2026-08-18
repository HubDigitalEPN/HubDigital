<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Traspaso de los especímenes de un lote recibido al módulo que custodia la colección.
 *
 * La implementación es lo único que conoce ambos bounded contexts; desde aquí el
 * ingreso se ve como un servicio externo más, igual que la notificación o la firma.
 */
interface IngresoColeccionPort
{
    /**
     * Ingresa a la colección los especímenes de la matriz de la solicitud dada.
     *
     * Debe ser idempotente: aprobar dos veces la misma recepción no puede duplicar
     * el lote en la colección.
     *
     * @param  string  $solicitudId  Solicitud cuya recepción física fue aprobada.
     * @param  string  $estadoColeccion  Régimen de tenencia del ingreso (Temporal | Permanente | Cuarentena).
     */
    public function ingresarLote(string $solicitudId, string $estadoColeccion): ResultadoIngresoColeccion;

    /**
     * Qué hay realmente en la colección para este lote.
     *
     * Permite que la pantalla de recepción muestre el resultado del ingreso en vez de
     * limitarse a prometerlo.
     */
    public function resumenDeLote(string $solicitudId): ResumenIngresoColeccion;

    /**
     * ¿Este lote ya tiene material en la colección?
     *
     * Es la guarda que impide borrar una matriz cuyos especímenes ya cruzaron: sin ella,
     * el trámite desaparece y los especímenes quedan huérfanos, sin nada que los ate a
     * su procedencia. Ya ocurrió con 13 de ellos.
     */
    public function loteYaIngresado(string $solicitudId): bool;

    /**
     * Ata cada fila de la matriz al espécimen que produjo.
     *
     * Backfill del material que entró antes de que la junta existiera. Verifica el orden
     * antes de escribir; con `$simular` no escribe nada y solo informa qué haría.
     */
    public function vincularRegistros(string $solicitudId, bool $simular = true): ResultadoVinculacionDeposito;

    /**
     * Recupera los campos Darwin Core que se perdieron en los ingresos antiguos.
     *
     * Relee la matriz y rellena solo las columnas vacías del material ya ingresado.
     * Con `$simular` no escribe: informa cuántas columnas llenaría.
     *
     * @return array{especimenesTocados: int, columnasEscritas: int}
     */
    public function sanearDatosDwC(string $solicitudId, bool $simular = true): array;

    /**
     * Marca para revisión el material cuyo trámite de origen ya no existe.
     *
     * @return int Espécimenes marcados.
     */
    public function marcarHuerfanos(string $motivo): int;

    /**
     * Marca como devueltos los especímenes de un lote que volvió a su depositante.
     *
     * No los borra de la colección: quedan con su régimen cambiado y la fecha de
     * salida, porque el rastro de qué estuvo bajo custodia es documentación.
     *
     * @return int Espécimenes efectivamente devueltos.
     */
    public function devolverLote(string $solicitudId, \DateTimeImmutable $devueltoEn): int;
}
