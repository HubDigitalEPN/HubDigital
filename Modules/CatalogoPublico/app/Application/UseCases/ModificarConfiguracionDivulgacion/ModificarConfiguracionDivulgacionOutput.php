<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion;

final readonly class ModificarConfiguracionDivulgacionOutput
{
    /** @param list<string> $occurrenceIDsModificados */
    private function __construct(
        public array $occurrenceIDsModificados,
    ) {}

    /** @param list<string> $occurrenceIDs */
    public static function fromPrimitives(array $occurrenceIDs): self
    {
        return new self(occurrenceIDsModificados: $occurrenceIDs);
    }
}
