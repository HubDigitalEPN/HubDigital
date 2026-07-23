<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;

/**
 * Resuelve quién origina la petición HTTP actual, para que la trazabilidad atribuya cada
 * movimiento a su actor real y no a un genérico. Orden de resolución:
 *
 * 1. Token Sanctum con la habilidad 'esp32' (ver routes/api.php) → esp32 sin id: es el
 *    dispositivo IoT, aunque el token pertenezca técnicamente a un usuario.
 * 2. Usuario autenticado → curador, con su id de usuario.
 * 3. Sesión efímera de visitante (abierta por QR firmado, ver VisitanteSesionMiddleware)
 *    → visitante, con su visitante_id.
 * 4. Nada de lo anterior (consola, procesos internos) → esp32 sin id.
 */
class HttpSeguridadContextoAdapter implements ContextoEjecucionPort
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Session $session,
    ) {}

    public function actorRol(): ActorRol
    {
        if ($this->esDispositivoEsp32()) {
            return ActorRol::Esp32;
        }

        if ($this->auth->guard()->check()) {
            return ActorRol::Curador;
        }

        if ($this->visitanteIdEnSesion() !== null) {
            return ActorRol::Visitante;
        }

        return ActorRol::Esp32;
    }

    public function actorId(): ?string
    {
        if ($this->esDispositivoEsp32()) {
            return null;
        }

        $guard = $this->auth->guard();
        if ($guard->check()) {
            $id = $guard->id();

            return $id !== null ? (string) $id : null;
        }

        return $this->visitanteIdEnSesion();
    }

    /**
     * Distingue las peticiones del dispositivo IoT: llegan autenticadas con un token Sanctum
     * personal que porta la habilidad 'esp32'. Se exige PersonalAccessToken explícitamente
     * porque el TransientToken de una sesión SPA responde can() === true para todo.
     */
    private function esDispositivoEsp32(): bool
    {
        $user = $this->auth->guard()->user();
        if ($user === null || ! method_exists($user, 'currentAccessToken')) {
            return false;
        }

        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken && $token->can('esp32');
    }

    /**
     * El middleware 'visitante' ya limpia la sesión cuando expira, pero este adaptador se
     * puede resolver fuera de esas rutas (p. ej. otra pantalla con la sesión vencida aún
     * poblada), así que revalida la expiración antes de atribuir el movimiento al visitante.
     */
    private function visitanteIdEnSesion(): ?string
    {
        $visitanteId = $this->session->get('visitante_id');
        $expiraEn = $this->session->get('visitante_expira_en');

        if ($visitanteId === null || $expiraEn === null || now()->getTimestamp() > (int) $expiraEn) {
            return null;
        }

        return (string) $visitanteId;
    }
}
