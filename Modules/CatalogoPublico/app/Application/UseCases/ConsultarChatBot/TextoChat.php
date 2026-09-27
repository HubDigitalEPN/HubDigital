<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Str;

/** Normaliza lenguaje corriente sin alterar nombres científicos usados en consultas. */
final class TextoChat
{
    /** Conserva la afirmación tras un contraste y separa los términos negados. */
    public function contraste(string $texto): array
    {
        $ascii = Str::lower(Str::ascii($texto));
        $sino = preg_split('/\bsino\b/u', $ascii, 2);
        if (count($sino) === 2) {
            $positive = $this->normalizar($sino[1]);
            if (preg_match('/\b(quiero|deseo|busco|necesito)\b/u', $sino[0], $verb)
                && ! preg_match('/^(quiero|deseo|busco|necesito)\b/u', $positive)) {
                $positive = $verb[1].' '.$positive;
            }
            return ['positivo' => $positive, 'negado' => $this->normalizar($sino[0])];
        }
        $positive = $negative = [];
        foreach (preg_split('/\s*(?:[,;]|\bpero\b)\s*/u', $ascii) ?: [] as $clause) {
            $clause = $this->normalizar($clause);
            if ($clause === '') {
                continue;
            }
            if (preg_match('/^no\s+(?:puedo|logro|consigo|se)\b/u', $clause)) {
                $positive[] = $clause;
                continue;
            }
            if (preg_match('/^(?:no|sin|excepto)\b/u', $clause)) {
                $negative[] = $clause;
                continue;
            }
            $parts = preg_split('/\b(?:no|sin|excepto)\b/u', $clause, 2);
            $positive[] = trim($parts[0]);
            if (count($parts) === 2) {
                $negative[] = trim($parts[1]);
            }
        }
        return ['positivo' => trim(implode(' ', $positive)), 'negado' => trim(implode(' ', $negative))];
    }

    public function normalizar(string $texto): string
    {
        $ascii = Str::lower(Str::ascii($texto));
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^a-z0-9\s]/', ' ', $ascii) ?? '') ?? '');
    }

    /** @return list<string> */
    public function tokens(string $texto, array $sinonimos = []): array
    {
        $stop = ['que', 'como', 'para', 'con', 'los', 'las', 'una', 'uno', 'unas', 'unos', 'quiero', 'quisiera', 'deseo', 'gustaria', 'me', 'mi', 'ya', 'debo', 'tengo', 'necesito', 'puedo', 'saber', 'del', 'por', 'cual', 'mis', 'hay', 'esto', 'esta', 'algo', 'son'];
        $tokens = [];
        foreach (explode(' ', $this->normalizar($texto)) as $token) {
            if (strlen($token) < 3 || in_array($token, $stop, true)) {
                continue;
            }
            $token = $sinonimos[$token] ?? $token;
            // Este recorte solo se usa para intención; nunca modifica el taxón consultado.
            $tokens[] = preg_replace('/(aciones|acion|mente|ados|adas|ando|iendo|ares|eres|ires|os|as|es|ar|er|ir|o|a)$/', '', $token) ?: $token;
        }
        return array_values(array_unique($tokens));
    }
}
