<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidacionManualCuraduria;

use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\NormalizadorCamposDwC;

/**
 * Permite al curador sanar una celda que la normalización marcó como anómala, en lugar
 * de devolver el trámite completo al depositante por un dígito de más.
 *
 * El valor que teclea el curador pasa por el mismo normalizador que se aplicó a la
 * matriz original: si tampoco es válido, se rechaza. Así no se puede colar por la vía
 * curatorial un dato que el sistema habría rechazado por la vía normal.
 */
final class ValidacionManualCuraduriaHandler
{
    public function __construct(
        private readonly MatrizEspeciesRepositoryInterface $matrizRepo,
        private readonly NormalizadorCamposDwC $normalizador,
        private readonly TransactionManagerPort $transactionManager,
    ) {}

    /**
     * @throws \DomainException Si no hay matriz, el registro no existe, el campo no
     *                          estaba marcado como anómalo o el valor sigue sin ser válido.
     */
    public function __invoke(ValidacionManualCuraduriaInput $input): ValidacionManualCuraduriaOutput
    {
        $matriz = $this->matrizRepo->buscarPorSolicitudId($input->solicitudId);

        if ($matriz === null) {
            throw new \DomainException('La solicitud no tiene una matriz de especímenes asociada');
        }

        $registros = $matriz->registros();
        $registro = $registros[$input->registroId] ?? null;

        if ($registro === null) {
            throw new \DomainException("El registro \"{$input->registroId}\" no pertenece a esta matriz");
        }

        $valorNormalizado = $this->normalizarValor($input->campo, $input->valor);
        $valorAnterior = $registro->datosDwC()[$input->campo] ?? null;

        $registro->corregirCeldaAnomala(
            campo: $input->campo,
            valorNormalizado: $valorNormalizado,
            curadorId: $input->curadorId,
            corregidoEn: new \DateTimeImmutable,
        );

        $this->transactionManager->executeTransactional(function () use ($matriz): void {
            $this->matrizRepo->guardar($matriz);
        });

        return new ValidacionManualCuraduriaOutput(
            campo: $input->campo,
            valorAnterior: $valorAnterior,
            valorGuardado: $valorNormalizado,
            celdasAnomalasRestantes: $this->contarCeldasAnomalas($matriz),
        );
    }

    /**
     * Pasa el valor tecleado por el normalizador de dominio y exige que salga limpio.
     *
     * Se normaliza el campo aislado, no el registro entero: solo interesa saber si ese
     * valor concreto es admisible, sin arrastrar anomalías de otras celdas.
     */
    private function normalizarValor(string $campo, string $valor): mixed
    {
        if (trim($valor) === '') {
            throw new \DomainException("El valor de \"{$campo}\" no puede quedar vacío");
        }

        $resultado = $this->normalizador->normalizar([$campo => $valor]);

        foreach ($resultado->cambios as $cambio) {
            if (($cambio['campo'] ?? null) === $campo && ! empty($cambio['invalido'])) {
                $motivo = $cambio['mensaje'] ?? 'no supera la validación del campo';
                throw new \DomainException("El valor introducido para \"{$campo}\" {$motivo}");
            }
        }

        return $resultado->registro[$campo] ?? $valor;
    }

    private function contarCeldasAnomalas(object $matriz): int
    {
        $total = 0;
        foreach ($matriz->registros() as $registro) {
            $total += count($registro->camposAnomalos());
        }

        return $total;
    }
}
