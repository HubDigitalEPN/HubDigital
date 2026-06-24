<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\GeneradorMarcaAguaPort;

/**
 * Marca de agua volátil para Behat. No procesa imagen: registra el texto con
 * el que se marcó cada contenido para poder verificar la regla de autoría.
 */
final class FakeGeneradorMarcaAgua implements GeneradorMarcaAguaPort
{
    /** @var list<string> */
    private array $textosAplicados = [];

    public function aplicar(string $contenido, string $texto): string
    {
        $this->textosAplicados[] = $texto;

        return $contenido.'::marca::'.$texto;
    }

    /** @return list<string> */
    public function textosAplicados(): array
    {
        return $this->textosAplicados;
    }

    public function seAplicoTexto(string $texto): bool
    {
        return in_array($texto, $this->textosAplicados, true);
    }
}
