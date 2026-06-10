<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Gateways;

use App\Models\User;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;

/**
 * Adaptador para obtener el correo electrónico del investigador desde el modelo User de la aplicación.
 *
 * Implementa {@see InvestigadorEmailPort} consultando la tabla de usuarios principal.
 */
final class LaravelUserInvestigadorEmailAdapter implements InvestigadorEmailPort
{
    /**
     * Recupera el email de un investigador por su identificador.
     */
    public function obtenerEmail(string $investigadorId): string
    {
        return User::findOrFail($investigadorId)->email;
    }
}
