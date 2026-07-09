<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen;

use Modules\CatalogoPublico\Application\Ports\AlmacenamientoImagenesPort;
use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Application\Ports\GeneradorMarcaAguaPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorJerarquiaDeEspecimenPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Domain\Entities\ImagenTaxonomica;
use Modules\CatalogoPublico\Domain\Repositories\ImagenPorDefectoRepositoryInterface;
use Modules\CatalogoPublico\Domain\Repositories\ImagenTaxonomicaRepositoryInterface;
use Modules\CatalogoPublico\Domain\Services\PropagadorImagenPorDefecto;
use Modules\CatalogoPublico\Domain\ValueObjects\AutorImagen;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;
use RuntimeException;

final class SubirImagenEspecimenHandler
{
    public function __construct(
        private readonly ImagenTaxonomicaRepositoryInterface $repoImagenes,
        private readonly ImagenPorDefectoRepositoryInterface $repoDefectos,
        private readonly AlmacenamientoImagenesPort $almacenamiento,
        private readonly GeneradorMarcaAguaPort $marcaAgua,
        private readonly ProveedorJerarquiaDeEspecimenPort $jerarquias,
        private readonly PropagadorImagenPorDefecto $propagador,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
    ) {}

    public function handle(SubirImagenEspecimenInput $input): SubirImagenEspecimenOutput
    {
        // R1: el autor (nombre + apellido) se valida antes de almacenar nada.
        $autor = AutorImagen::crear($input->nombreAutor, $input->apellidoAutor);

        $jerarquia = $this->jerarquias->obtener($input->occurrenceID);

        if ($jerarquia === null) {
            throw new RuntimeException(
                "El espécimen '{$input->occurrenceID}' no existe en la tabla de divulgación"
            );
        }

        // R2: se aplica la marca de agua con el nombre del autor antes de persistir.
        $contenidoConMarca = $this->marcaAgua->aplicar($input->contenido, $autor->nombreCompleto());
        $nombreDeseado = $this->componerNombreArchivo(
            $input->occurrenceID,
            $input->vistaSufijo,
            $input->nombreArchivo,
        );
        $archivo = $this->almacenamiento->guardar($contenidoConMarca, $nombreDeseado);

        $id = $this->repoImagenes->nextIdentity();
        $imagen = ImagenTaxonomica::subir($id, $input->occurrenceID, $archivo, $autor);

        // R4/R5: la portada bubbleea solo a especie y género sin defecto previo.
        $clavesConDefecto = $this->repoDefectos->clavesConDefecto([
            [RangoTaxonomico::Species, $jerarquia->nombreEspecie()],
            [RangoTaxonomico::Genus, $jerarquia->padreDeEspecie()],
        ]);
        $nuevosDefectos = $this->propagador->nuevosDefectos($jerarquia, $id, $clavesConDefecto);

        $this->transactionManager->executeTransactional(function () use ($imagen, $nuevosDefectos): void {
            $this->repoImagenes->guardar($imagen);

            foreach ($nuevosDefectos as $defecto) {
                $this->repoDefectos->guardar($defecto);
            }

            foreach ($imagen->pullEvents() as $event) {
                $this->eventPublisher->publish($event);
            }
        });

        $niveles = array_map(
            fn ($defecto): string => $defecto->nivel()->value.':'.$defecto->valorTaxon(),
            $nuevosDefectos,
        );

        return SubirImagenEspecimenOutput::crear(
            imagenId: $id->toString(),
            nombreArchivo: $archivo->nombreOriginal,
            occurrenceID: $input->occurrenceID,
            autor: $autor->nombreCompleto(),
            nivelesActualizados: $niveles,
        );
    }

    private function componerNombreArchivo(string $occurrenceID, string $vistaSufijo, string $nombreOriginal): string
    {
        if ($vistaSufijo === '') {
            return $nombreOriginal;
        }

        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
        $ocurrencia = preg_replace('/[^A-Za-z0-9_.-]/', '_', $occurrenceID) ?? $occurrenceID;
        $sufijo = preg_replace('/[^A-Za-z0-9]/', '', $vistaSufijo) ?? $vistaSufijo;

        return $extension === ''
            ? $ocurrencia.'_'.$sufijo
            : $ocurrencia.'_'.$sufijo.'.'.$extension;
    }
}
