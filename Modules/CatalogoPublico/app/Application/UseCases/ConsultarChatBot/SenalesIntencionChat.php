<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Rasgos de dominio y tema separados del texto institucional de las respuestas. */
final class SenalesIntencionChat
{
    private const DOMAIN = [
        'deposito' => '/\b(deposit\w*|custodi\w*|dejar|dejo|guarde|guardar)\b/u',
        'donacion' => '/\b(dona\w*|regal\w*|ceder|cesion|gratuit\w*|transferir|transferencia de propiedad)\b/u',
        'catalogo' => '/\b(catalog\w*|taxon\w*|especie\w*|genero\w*|familia\w*|registro\w* public\w*|divulgad\w*|busco ejemplares)\b/u',
        'acceso' => '/\b(acces\w*|ingres\w*|entrar|autentic\w*|sesion|clave|contrasen\w*|usuario|cuenta)\b/u',
        'contacto' => '/\b(contact\w*|correo|escrib\w*|comunic\w*|hablar con|ayuda humana)\b/u',
    ];

    private const TOPIC = [
        'documentos' => '/\b(document\w*|documet\w*|papel\w*|pdf|archiv\w*|adjunt\w*|anexo\w*|respaldo\w*|subir)\b/u',
        'requisitos' => '/\b(requis\w*|requic\w*|condicion\w*|tramites necesarios)\b/u',
        'entrega' => '/\b(entreg\w*|llevar|llevo|traslad\w*|recepcion\w*|acerc\w*)\b/u',
        'revision' => '/\b(revis\w*|aprob\w*|correc\w*|evalu\w*|ya mand\w*|ya envi\w*|envi\w* (?:solicitud|tramite)|que sigue|y luego|despues|curaduria ahora)\b/u',
        'estado' => '/\b(estado|avance|seguim\w*|como va|mis solicitudes)\b/u',
        'registro' => '/\b(crear cuenta|abrir cuenta|cuenta nueva|usuario nuevo|nuevo usuario|registrarme|registrarse)\b/u',
        'firma' => '/\b(firm\w*|firma electronica)\b/u',
        'permisos' => '/\b(permis\w*|moviliz\w*|autoriz\w*|guia de transporte)\b/u',
        'procedencia' => '/\b(proceden\w*|origen del material)\b/u',
    ];

    public function __construct(private readonly TextoChat $texto) {}

    public function analizar(string $pregunta, string $negado, array $nodes, ?int $anterior): array
    {
        $positive = $this->texto->normalizar($pregunta);
        $negative = $this->texto->normalizar($negado);
        $explicit = $this->detectar(self::DOMAIN, $positive);
        $negated = $this->detectar(self::DOMAIN, $negative);
        $topics = $this->detectarTodos(self::TOPIC, $positive);
        $byId = [];
        foreach ($nodes as $node) $byId[(int) $node->id] = $node;
        $contextDomain = $anterior !== null && isset($byId[$anterior])
            ? $this->perfil($byId[$anterior], $byId)['domain'] : null;
        $switch = (bool) preg_match('/\b(ahora|cambiando de tema|otra cosa|sobre donaciones|sobre depositos)\b/u', $positive);
        $domain = $explicit ?? ($switch ? null : $contextDomain);
        $clarify = [];
        if ($domain === null && preg_match('/^(?:los? )?(?:documentos|papeles|requisitos|requicitos)$/u', $positive)) {
            $clarify = preg_match('/requis/u', $positive)
                ? ['deposito_requisitos', 'donacion_requisitos'] : ['documentos', 'donacion_requisitos'];
        } elseif ($domain === null && preg_match('/^(?:que necesito para eso|necesito ayuda con unos animales|quiero entregar algo|necesito hacer un tramite)$/u', $positive)) {
            $clarify = ['deposito', 'donacion', 'catalogo'];
        }
        return ['domain' => $domain, 'explicit_domain' => $explicit, 'context_domain' => $contextDomain,
            'negated_domain' => $negated, 'topics' => $topics, 'switch' => $switch,
            'short' => count(array_filter(explode(' ', $positive))) <= 3, 'clarify_slugs' => $clarify];
    }

    public function perfil(object $node, array $byId): array
    {
        $root = $node;
        for ($depth = 0; $root->parent_id !== null && isset($byId[(int) $root->parent_id]) && $depth < 20; $depth++) {
            $root = $byId[(int) $root->parent_id];
        }
        $slug = (string) $node->slug;
        $topic = match (true) {
            str_starts_with($slug, 'documentos_firma') => 'firma',
            str_starts_with($slug, 'documentos_permisos') => 'permisos',
            $slug === 'documentos' => 'documentos',
            str_ends_with($slug, '_requisitos') => 'requisitos',
            $slug === 'revision_estado' => 'estado',
            $slug === 'revision' => 'revision',
            $slug === 'entrega' => 'entrega',
            $slug === 'acceso_registro' => 'registro',
            $slug === 'procedencia' => 'procedencia',
            default => null,
        };
        return ['domain' => (string) $root->slug, 'topic' => $topic,
            'specificity' => $node->parent_id === null ? 0 : 1];
    }

    /** Nodos con la misma tarea conservan respuestas editables distintas sin duplicar la intención. */
    public function intencion(string $slug): string
    {
        return match ($slug) {
            'revision_estado' => 'revision',
            'catalogo_busqueda' => 'catalogo',
            default => $slug,
        };
    }

    public function bonus(array $signals, array $profile): float
    {
        $domain = $profile['domain'];
        $topic = $profile['topic'];
        $score = 0.0;
        if ($signals['explicit_domain'] !== null) {
            $score += $signals['explicit_domain'] === $domain ? .26 : -.24;
        } elseif ($signals['domain'] !== null) {
            $score += $signals['domain'] === $domain ? .12 : -.08;
        }
        if ($signals['negated_domain'] === $domain) $score -= .36;
        if ($signals['topics'] !== []) {
            $matched = $topic !== null && in_array($topic, $signals['topics'], true);
            if ($matched) $score += .32;
            elseif ($topic === null) $score -= .08;
            else $score -= .06;
            if (in_array('documentos', $signals['topics'], true) && $topic === 'requisitos'
                && $domain === 'donacion' && $signals['domain'] === null) $score += .24;
            if (in_array('documentos', $signals['topics'], true) && $topic === 'requisitos'
                && $domain === 'donacion' && $signals['domain'] === 'donacion') $score += .28;
            if (in_array('requisitos', $signals['topics'], true) && $topic === 'documentos') $score += .08;
            if (in_array('estado', $signals['topics'], true) && $topic === 'revision') $score += .10;
            if ($topic === 'documentos' && (in_array('permisos', $signals['topics'], true)
                || in_array('firma', $signals['topics'], true))) $score -= .12;
            if ($topic === 'documentos' && in_array('revision', $signals['topics'], true)) $score -= .18;
        } elseif ($topic !== null) {
            $score -= .10;
        } elseif ($signals['explicit_domain'] === $domain) {
            $score += .05;
        }
        return round($score, 4);
    }

    private function detectar(array $patterns, string $text): ?string
    {
        foreach ($patterns as $name => $pattern) if (preg_match($pattern, $text)) return $name;
        return null;
    }

    private function detectarTodos(array $patterns, string $text): array
    {
        $found = [];
        foreach ($patterns as $name => $pattern) if (preg_match($pattern, $text)) $found[] = $name;
        return $found;
    }
}
