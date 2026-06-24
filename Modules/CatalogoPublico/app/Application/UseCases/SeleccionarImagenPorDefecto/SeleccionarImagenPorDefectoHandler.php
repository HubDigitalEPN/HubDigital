<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto;

use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Domain\Entities\ImagenPorDefectoDeTaxon;
use Modules\CatalogoPublico\Domain\Exceptions\NivelNoSobrescribibleException;
use Modules\CatalogoPublico\Domain\Repositories\ImagenPorDefectoRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final class SeleccionarImagenPorDefectoHandler
{
    public function __construct(
        private readonly ImagenPorDefectoRepositoryInterface $repoDefectos,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    public function handle(SeleccionarImagenPorDefectoInput $input): SeleccionarImagenPorDefectoOutput
    {
        $nivel = RangoTaxonomico::desde($input->nivel);

        // R7: solo género y especie admiten portada; el resto se rechaza.
        if (! $nivel->admiteImagen()) {
            throw new NivelNoSobrescribibleException(
                "El nivel '{$nivel->value}' no admite imagen por defecto; solo género y especie la admiten"
            );
        }

        $imagenId = ImagenTaxonomicaId::fromString($input->imagenId);
        $existente = $this->repoDefectos->obtener($nivel, $input->valorTaxon);

        $defecto = $existente ?? ImagenPorDefectoDeTaxon::asignar($nivel, $input->valorTaxon, $imagenId);

        // R6: si ya había portada, se sobrescribe con la imagen elegida.
        if ($existente !== null) {
            $defecto->sobrescribirCon($imagenId);
        }

        $this->transactionManager->executeTransactional(function () use ($defecto): void {
            $this->repoDefectos->guardar($defecto);
        });

        return SeleccionarImagenPorDefectoOutput::crear(
            nivel: $nivel->value,
            valorTaxon: $input->valorTaxon,
            imagenId: $imagenId->toString(),
        );
    }
}
