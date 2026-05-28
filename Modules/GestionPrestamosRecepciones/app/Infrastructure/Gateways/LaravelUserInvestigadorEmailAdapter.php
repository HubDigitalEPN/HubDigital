<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Gateways;

use App\Models\User;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;

final class LaravelUserInvestigadorEmailAdapter implements InvestigadorEmailPort
{
    public function obtenerEmail(string $investigadorId): string
    {
        return User::findOrFail($investigadorId)->email;
    }
}
