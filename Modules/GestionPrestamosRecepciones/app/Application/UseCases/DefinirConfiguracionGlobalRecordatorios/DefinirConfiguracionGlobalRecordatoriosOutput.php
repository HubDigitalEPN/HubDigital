<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;

final readonly class DefinirConfiguracionGlobalRecordatoriosOutput
{
    /**
     * @param  list<int>  $diasAntes
     */
    public function __construct(
        public string $configuracionId,
        public array $diasAntes,
    ) {}

    public static function from(ConfiguracionGlobalRecordatorios $configuracion): self
    {
        return new self(
            configuracionId: (string) $configuracion->id(),
            diasAntes: $configuracion->diasAntes(),
        );
    }
}
