<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento;

use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoEspecimenesPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ItemPrestamoVista;
use Modules\GestionPrestamosRecepciones\Domain\Entities\ItemPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PatenteAnualRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Manejador del caso de uso para consultar el documento completo de un acta de
 * préstamo (acta + solicitud + ítems), listo para renderizar.
 *
 * {@see ConsultarActaDocumentoInput}
 * {@see ConsultarActaDocumentoOutput}
 */
final class ConsultarActaDocumentoHandler
{
    /**
     * @param  ActaPrestamoRepositoryInterface  $actaRepo  Repositorio de actas de préstamo.
     * @param  SolicitudPrestamoRepositoryInterface  $solicitudRepo  Repositorio de solicitudes de préstamo.
     */
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly UsuarioNombrePort $usuarios,
        private readonly PatenteAnualRepositoryInterface $patentes,
        private readonly CatalogoEspecimenesPort $catalogo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param  ConsultarActaDocumentoInput  $input  Datos de entrada.
     * @return ConsultarActaDocumentoOutput|null Documento del acta, o null si no existe.
     */
    public function handle(ConsultarActaDocumentoInput $input): ?ConsultarActaDocumentoOutput
    {
        $acta = $this->actaRepo->buscarPorId(ActaPrestamoId::fromString($input->actaId));

        if ($acta === null) {
            return null;
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());

        // Los datos descriptivos (familia, procedencia) se leen del inventario al
        // renderizar y no del snapshot: así el acta los muestra también en préstamos
        // creados antes de esta columna. Una sola consulta para todos los ítems.
        $fichas = $solicitud === null ? [] : $this->catalogo->obtenerFichasParaActa(
            array_values(array_filter(array_map(
                static fn (ItemPrestamo $item) => $item->especimenId(),
                $solicitud->items(),
            )))
        );

        $items = $solicitud === null ? [] : array_values(array_map(
            function (ItemPrestamo $item) use ($fichas): ItemPrestamoVista {
                // Ítems anteriores a la conexión con el catálogo no tienen especimenId.
                $ficha = $fichas[$item->especimenId() ?? ''] ?? null;

                return new ItemPrestamoVista(
                    itemPrestamoId: (string) $item->id(),
                    codigoExterno: $item->especimenCodigoExterno(),
                    cantidadSolicitada: $item->cantidadSolicitada(),
                    nombre: $item->especimenSnapshot()['nombre_cientifico'] ?? null,
                    condicionesEspecificas: $item->condicionesEspecificas(),
                    especimenId: $item->especimenId(),
                    individualesDisponibles: $item->especimenSnapshot()['individuales_disponibles'] ?? null,
                    familia: $ficha?->familia,
                    sexo: $ficha?->sexo,
                    especie: $ficha?->especie,
                    provincia: $ficha?->provincia,
                    localidad: $ficha?->localidad,
                );
            },
            $solicitud->items(),
        ));

        $investigadorId = $solicitud?->investigadorId() ?? '';

        // Solo lectura para visualización: devuelve la patente del año o cadena vacía.
        // El bloqueo por patente faltante vive en la generación (firma y descarga).
        $anioPatente = (int) $acta->fechaInicio()->format('Y');
        $patente = $this->patentes->buscarCodigoPorAnio($anioPatente) ?? '';

        return new ConsultarActaDocumentoOutput(
            id: (string) $acta->id(),
            investigadorId: $investigadorId,
            nombreInvestigador: $investigadorId === '' ? null : $this->usuarios->obtenerNombre($investigadorId),
            numeroPrestamo: (string) $acta->codigoPrestamo(),
            tipoPrestamo: $acta->tipoPrestamo()->value,
            alcancePrestamo: $acta->alcancePrestamo()->value,
            fechaInicio: $acta->fechaInicio(),
            fechaFin: $acta->fechaFin(),
            condicionesGenerales: $acta->condicionesGenerales(),
            numeroSolicitud: $solicitud !== null ? (string) $solicitud->codigoPrestamo() : null,
            tituloEstudio: $solicitud?->tituloEstudio(),
            institucionAdscripcion: $solicitud?->institucionAdscripcion(),
            lineaInvestigacion: $solicitud?->lineaInvestigacion(),
            propositoPrestamo: $solicitud?->propositoPrestamo(),
            patente: $patente,
            items: $items,
        );
    }
}
