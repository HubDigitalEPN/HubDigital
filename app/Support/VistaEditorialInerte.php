<?php

declare(strict_types=1);

namespace App\Support;

/** Una copia de presentación sin JavaScript, envíos ni validaciones operativas. */
final class VistaEditorialInerte
{
    public static function sanear(string $html): string
    {
        // libxml no reconoce los nombres abreviados de Alpine (@click, :class).
        // Retirarlos antes de analizar evita que fragmentos del atributo se
        // conviertan en texto visible o rompan el menú móvil.
        $html = preg_replace('~<script\b(?![^>]*\btype=["\x27]application/json["\x27])[^>]*>.*?</script>~si', '', $html);
        $html = preg_replace('~\sx-cloak(?=[\s>])~i', ' hidden', $html);
        $html = preg_replace('~\s(?:wire:[\w.:-]+|x-[\w.:-]+|@[\w.:-]+|:[\w.:-]+|on\w+)\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27)~si', '', $html);
        $doc = new \DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NODEFDTD); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($anterior); }
        $xpath = new \DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('//script[not(@type="application/json")]|//link[@as="script"]|//iframe|//input[@type="hidden"]')) as $nodo) $nodo->parentNode?->removeChild($nodo);
        foreach (iterator_to_array($xpath->query('//*')) as $nodo) {
            foreach (iterator_to_array($nodo->attributes) as $atributo) {
                if (preg_match('/^(?:wire:|x-|on|@|:)/i', $atributo->name) || in_array($atributo->name, ['required', 'aria-required', 'pattern', 'min', 'max', 'minlength', 'maxlength', 'aria-invalid', 'action', 'formaction', 'autofocus'], true)) $nodo->removeAttribute($atributo->name);
            }
            if ($nodo->tagName === 'form') $nodo->setAttribute('novalidate', '');
            if ($nodo->tagName === 'button') $nodo->setAttribute('type', 'button');
            if (in_array($nodo->tagName, ['input', 'select', 'textarea'], true)) {
                $nodo->setAttribute('readonly', ''); $nodo->setAttribute('tabindex', '-1');
                if ($nodo->tagName === 'select' || $nodo->getAttribute('type') === 'file') $nodo->setAttribute('disabled', '');
            }
        }
        $doc->documentElement->setAttribute('data-vista-editorial', 'true');
        return $doc->saveHTML();
    }
}
