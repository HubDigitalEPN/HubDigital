<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use InvalidArgumentException;

/**
 * Valida y normaliza el valor que una edición masiva va a escribir en un campo,
 * y calcula los reemplazos de texto.
 *
 * Servicio puro. Es la única puerta por la que pasa un valor tecleado por el
 * curador antes de llegar al UPDATE: si algo no encaja con el tipo o con el
 * `varchar` de la columna, se lanza AQUÍ, con el lote todavía sin tocar. Dejar
 * que reviente Postgres significaría abortar la transacción entera sin poder
 * decir qué fila la rompió.
 */
final class NormalizadorValorCampoEspecimen
{
    /**
     * Normaliza el valor tecleado al que se guardará en la columna.
     *
     * Devuelve `null` para "vaciar el campo". El llamador ya ha decidido si
     * vaciar estaba permitido; aquí solo se comprueba que el campo lo admita.
     *
     * @throws InvalidArgumentException si la clave no es editable o el valor no
     *                                  encaja con el tipo o el tamaño del campo
     */
    public function normalizar(string $clave, ?string $valor): ?string
    {
        $campo = RegistroColumnasEspecimen::campoEditable($clave);
        if ($campo === null) {
            throw new InvalidArgumentException("El campo '{$clave}' no se puede editar en masa.");
        }

        $valor = $valor !== null ? trim($valor) : null;
        if ($valor === '' || $valor === null) {
            if (! $campo['admiteVacio']) {
                throw new InvalidArgumentException(
                    "El campo '{$clave}' no puede quedar vacío: es un dato obligatorio del espécimen."
                );
            }

            return null;
        }

        if ($campo['tipo'] === RegistroColumnasEspecimen::TIPO_BOOLEANO) {
            return $this->normalizarBooleano($clave, $valor);
        }

        if ($campo['maximo'] !== null && mb_strlen($valor) > $campo['maximo']) {
            throw new InvalidArgumentException(
                "El valor para '{$clave}' tiene ".mb_strlen($valor).' caracteres y el campo admite '.$campo['maximo'].'.'
            );
        }

        return $valor;
    }

    /**
     * Aplica un buscar/reemplazar a un valor concreto. Devuelve el valor tal
     * cual si no hay coincidencia, y `null` se propaga como `null`.
     *
     * `$buscar` es TEXTO PLANO, no una expresión regular: se escapa antes de
     * construir el patrón. Sin ese escape, un punto o un paréntesis escritos por
     * el curador se interpretarían como metacaracteres y arrasarían el campo en
     * todas las filas seleccionadas.
     *
     * `$palabraCompleta` evita el otro estropicio clásico: reemplazar "sp" por
     * "sp." convertiría "Aspidosperma" en "A.pidosperma".
     */
    public function reemplazarEn(
        ?string $valor,
        string $buscar,
        string $reemplazo,
        bool $distinguirMayusculas = true,
        bool $palabraCompleta = false,
    ): ?string {
        if ($valor === null || $buscar === '') {
            return $valor;
        }

        $patron = preg_quote($buscar, '/');
        if ($palabraCompleta) {
            $patron = '\b'.$patron.'\b';
        }

        $modificadores = 'u'.($distinguirMayusculas ? '' : 'i');

        $resultado = preg_replace('/'.$patron.'/'.$modificadores, $reemplazo, $valor);

        // preg_replace devuelve null ante un error del motor (por ejemplo texto
        // que no es UTF-8 válido). Conservar el valor original es preferible a
        // escribir null y vaciar el campo sin que nadie lo haya pedido.
        return $resultado ?? $valor;
    }

    /**
     * Comprueba que el resultado de un reemplazo sigue cabiendo en la columna.
     *
     * @throws InvalidArgumentException
     */
    public function verificarTamano(string $clave, string $valor, string $codigoCatalogo): void
    {
        $campo = RegistroColumnasEspecimen::campoEditable($clave);
        if ($campo === null || $campo['maximo'] === null) {
            return;
        }

        if (mb_strlen($valor) > $campo['maximo']) {
            throw new InvalidArgumentException(
                "El reemplazo deja el campo '{$clave}' del espécimen '{$codigoCatalogo}' con "
                .mb_strlen($valor).' caracteres, y solo admite '.$campo['maximo'].'. No se aplicó ningún cambio.'
            );
        }
    }

    /** Representación en texto que se guarda en la bitácora. */
    public function aTexto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        return (string) $valor;
    }

    private function normalizarBooleano(string $clave, string $valor): string
    {
        $normalizado = mb_strtolower($valor, 'UTF-8');

        return match ($normalizado) {
            'si', 'sí', 'yes', 'y', 's', '1', 'true', 't' => 'true',
            'no', 'n', '0', 'false', 'f' => 'false',
            default => throw new InvalidArgumentException(
                "El campo '{$clave}' solo admite sí o no; se recibió '{$valor}'."
            ),
        };
    }
}
