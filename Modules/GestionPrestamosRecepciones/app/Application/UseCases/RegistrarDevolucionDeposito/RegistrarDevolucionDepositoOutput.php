<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionDeposito;

/** Resultado de la devolución: estado del trámite y material que salió de la colección. */
final readonly class RegistrarDevolucionDepositoOutput
{
    public function __construct(
        public string $estado,
        public int $especimenesDevueltos,
    ) {}
}
