<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada;

use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenRepositoryInterface;
use RuntimeException;

final class ConsultarInformacionDivulgadaHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $repoEspecimen,
        private readonly EspecimenDivulgableRepositoryInterface $repoDivulgable,
    ) {}

    public function handle(ConsultarInformacionDivulgadaInput $input): ConsultarInformacionDivulgadaOutput
    {
        $divulgable = $this->repoDivulgable->buscarPorOccurrenceID($input->occurrenceID);

        if ($divulgable === null) {
            throw new RuntimeException(
                "El espécimen '{$input->occurrenceID}' no está sincronizado para divulgación"
            );
        }

        $especimen = $this->repoEspecimen->buscarPorOccurrenceID($input->occurrenceID);

        if ($especimen === null) {
            throw new RuntimeException(
                "El espécimen '{$input->occurrenceID}' no existe en la base interna"
            );
        }

        $datosVisibles = $divulgable->obtenerDatosVisibles($especimen);

        return ConsultarInformacionDivulgadaOutput::fromPrimitives($datosVisibles);
    }
}
