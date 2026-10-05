<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Solo intención, entidad pública y tres variantes durante 30 minutos. */
final class ContextoChat
{
    private const SESSION_KEY = 'portal_chat_context';

    public function reiniciar(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function obtener(): array
    {
        $context = session()->get(self::SESSION_KEY, []);
        if (! is_array($context) || (int) ($context['expires_at'] ?? 0) < time()) {
            session()->forget(self::SESSION_KEY);
            return [];
        }
        return $context;
    }

    public function guardar(array $previous, array $result): void
    {
        $context = [
            'expires_at' => time() + 1800,
            'node_id' => isset($result['node_id']) ? (int) $result['node_id'] : ($previous['node_id'] ?? null),
            'variants' => array_slice($previous['variants'] ?? [], -3),
            'entities' => in_array($result['intent'] ?? null, ['portal.acceso_publico', 'portal.limpiar'], true)
                ? []
                : (($result['intent'] ?? null) === 'catalogo.aclaracion'
                ? ($previous['entities'] ?? [])
                : (in_array($result['fuente'] ?? null, ['catalogo', 'portal'], true)
                    ? array_intersect_key($result['entidades'] ?? ($previous['entities'] ?? []), array_flip(['taxon', 'provincia', 'provincia_excluida', 'localidad', 'pais', 'codigo', 'mes', 'desde', 'hasta', 'fecha_precision', 'ubicacion', 'identificacion', 'elev_desde', 'elev_hasta']))
                    : (($result['fuente'] ?? '') === 'conversacion' ? ($previous['entities'] ?? []) : []))),
        ];
        if (isset($result['variant_id']) && $result['variant_id'] !== null) {
            $context['variants'] = array_slice([...$context['variants'], (int) $result['variant_id']], -3);
        }
        session()->put(self::SESSION_KEY, $context);
    }
}
