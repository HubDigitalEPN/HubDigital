<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito;

/**
 * Entrada de la ingesta de un lote depositado.
 *
 * Deliberadamente en primitivos: es el contrato que cruza desde
 * GestionPrestamosRecepciones, y ninguno de los dos módulos debe conocer los tipos
 * de dominio del otro.
 *
 * Cada fila puede traer `registroId`, el uuid de la fila de matriz que la declaró. Es la
 * clave de idempotencia buena: la anterior derivaba del número de solicitud y la
 * POSICIÓN de la fila, de modo que añadir o quitar una fila entre dos aprobaciones
 * corría todos los índices y duplicaba el lote entero. Es opcional para no romper a
 * quien construya filas a mano, pero sin él la idempotencia vuelve a ser la frágil.
 *
 * @param  array<int, array{indice: int, registroId?: string, datosDwC: array<string, mixed>, estadoRegistro: string, motivoJustificacion?: string|null, nombreCientificoCanonico?: string|null}>  $filas
 */
final readonly class IngresarLoteDepositoInput
{
    public function __construct(
        public string $numeroSolicitud,
        /**
         * Código QR del lote recibido, y solo eso.
         *
         * Antes este campo valía o el código QR o —si aún no había recepción física— el
         * número de la solicitud, así que leer la columna no permitía saber cuál de las
         * dos cosas se estaba mirando. El número de solicitud ya vive en su propia
         * columna, de modo que aquí sobra: null significa que el lote todavía no tiene
         * código QR, que es una respuesta legítima.
         *
         * El identificador del lote no viaja porque no hace falta: la recepción es 1:1
         * con la solicitud (índice único sobre `solicitud_deposito_id`), y el espécimen
         * ya guarda de qué solicitud viene.
         */
        public ?string $codigoQrLote,
        public string $estadoCustodia,
        public array $filas,
        public ?string $entidadDepositanteId = null,
        /** Identidad del trámite; sobrevive a las renumeraciones del número de solicitud. */
        public ?string $solicitudDepositoId = null,
        /** Depósito o Donación: el régimen de custodia por sí solo ya no lo distingue. */
        public ?string $tipoTramite = null,
        /** Cuándo se recibió físicamente el material. */
        public ?\DateTimeImmutable $recibidoEn = null,
        /**
         * Quién depositó: nombre de la persona, su institución y su correo.
         *
         * La colección lo convierte en una entidad depositante propia. Es opcional para
         * no romper a quien construya lotes a mano, pero sin él el material entra sin
         * depositante y averiguar de quién es exige volver al módulo de recepciones.
         */
        public ?string $depositanteNombre = null,
        public ?string $depositanteInstitucion = null,
        public ?string $depositanteEmail = null,
        /**
         * Permisos ambientales del trámite.
         *
         * Son del depósito entero, no de cada fila: la plantilla dejó de pedirlos por
         * espécimen precisamente porque preguntarlos dos veces producía respuestas
         * distintas para un mismo lote.
         */
        public ?string $permisoRecoleccion = null,
        public ?string $permisoMovilizacion = null,
        /**
         * Curador que dio entrada al material.
         *
         * La geografía del trámite NO viaja a propósito: `provincia_origen` describe el
         * depósito entero y un depósito puede abarcar varias provincias, mientras que la
         * matriz declara la localidad fila a fila, que es donde corresponde. Rellenar una
         * desde la otra reintroduciría la doble fuente que se acaba de eliminar con los
         * permisos.
         */
        public ?string $registradoPor = null,
    ) {}
}
