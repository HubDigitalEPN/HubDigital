<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Contexto de sesión acotado a entidades públicas, tres variantes y una petición pendiente durante 30 minutos. */
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
            // Una consulta global puede no tener filtros, pero conserva qué se contó.
            'ultima_unidad' => match ($result['intent'] ?? '') {
                'catalogo.count' => 'registros', 'catalogo.species' => 'especies',
                'catalogo.genera' => 'géneros', 'catalogo.families' => 'familias',
                default => ($result['fuente'] ?? '') === 'conversacion' ? ($previous['ultima_unidad'] ?? null) : null,
            },
            'solicitud_pendiente' => array_key_exists('solicitud_pendiente', $result)
                ? (is_string($result['solicitud_pendiente']) && mb_strlen($result['solicitud_pendiente']) <= 500 ? $result['solicitud_pendiente'] : null)
                : ((in_array($result['intent'] ?? '', ['portal.limpiar', 'portal.acceso_publico'], true)
                    || (str_starts_with($result['intent'] ?? '', 'catalogo.') && ($result['intent'] ?? '') !== 'catalogo.aclaracion'))
                    ? null : ($previous['solicitud_pendiente'] ?? null)),
            'entities' => in_array($result['intent'] ?? null, ['portal.acceso_publico', 'portal.limpiar'], true)
                ? []
                : (($result['intent'] ?? null) === 'catalogo.aclaracion'
                ? ($previous['entities'] ?? [])
                : (in_array($result['fuente'] ?? null, ['catalogo', 'portal'], true)
                    ? array_intersect_key($result['entidades'] ?? ($previous['entities'] ?? []), array_flip(['taxon', 'provincia', 'localidad', 'pais', 'codigo', 'mes', 'desde', 'hasta', 'fecha_precision', 'ubicacion', 'identificacion', 'elev_desde', 'elev_hasta']))
                    : (($result['fuente'] ?? '') === 'conversacion' ? ($previous['entities'] ?? []) : []))),
        ];
        if (isset($result['variant_id']) && $result['variant_id'] !== null) {
            $context['variants'] = array_slice([...$context['variants'], (int) $result['variant_id']], -3);
        }
        session()->put(self::SESSION_KEY, $context);
    }

    public function continuarConsulta(string $pregunta): string
    {
        $unidad = $this->obtener()['ultima_unidad'] ?? null;
        if (! in_array($unidad, ['registros', 'especies', 'géneros', 'familias'], true)
            || ! preg_match('/\A[\s¿?]*y\s+en\s+(.+?)[?.!]*\z/iu', trim($pregunta), $lugar)) return $pregunta;
        $cuantos = in_array($unidad, ['especies', 'familias'], true) ? 'Cuántas' : 'Cuántos';
        return '¿'.$cuantos.' '.$unidad.' hay en '.trim($lugar[1]).'?';
    }
}
