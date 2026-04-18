<?php

declare(strict_types=1);

namespace App\Console\Commands\Behat;

use Illuminate\Console\Command;

class ScaffoldBehatContextCommand extends Command
{
    protected $signature = 'behat:scaffold
        {module      : Nombre del módulo (ej. GestionPrestamosRecepciones)}
        {capability  : Carpeta de capability (ej. RecepcionValidacionLotesEspecimenesYDatos)}
        {feature     : Nombre del .feature sin extensión (ej. recepcion_muestras_biologicas)}';

    protected $description = 'Genera el Context PHP desde un .feature y lo registra en behat.php';

    // Keywords españoles → tipo Behat
    private const PRIMARY_KEYWORDS = [
        'Dado' => 'Given', 'Dada' => 'Given', 'Dados' => 'Given', 'Dadas' => 'Given',
        'Cuando' => 'When',
        'Entonces' => 'Then',
    ];

    private const SECONDARY_KEYWORDS = ['Y', 'Pero', '*'];

    public function handle(): int
    {
        $module = $this->argument('module');
        $capability = $this->argument('capability');
        $feature = $this->argument('feature');

        $featurePath = "Modules/{$module}/tests/Behat/Features/{$capability}/{$feature}.feature";
        $contextClass = $this->featureNameToContextClass($feature);
        $namespace = "Modules\\{$module}\\Tests\\Behat\\Contexts\\{$capability}";
        $contextRelPath = "Modules/{$module}/tests/Behat/Contexts/{$capability}/{$contextClass}.php";

        // 1. Validar que la feature existe
        if (! file_exists(base_path($featurePath))) {
            $this->error("Feature no encontrada: {$featurePath}");

            return self::FAILURE;
        }
        $this->line("  <fg=green>✓</> Feature encontrada: <comment>{$featurePath}</comment>");

        // 2. Avisar si el context ya existe (se sobreescribe siempre)
        if (file_exists(base_path($contextRelPath))) {
            $this->warn("El context ya existía, se sobreescribirá: {$contextRelPath}");
        }

        // 3. Parsear steps del .feature
        $steps = $this->parseSteps(base_path($featurePath));

        // 4. Crear directorio si no existe
        $contextDir = dirname(base_path($contextRelPath));
        if (! is_dir($contextDir)) {
            mkdir($contextDir, 0755, true);
        }

        // 5. Escribir el archivo Context
        $phpContent = $this->buildContextFile($namespace, $contextClass, $steps);
        file_put_contents(base_path($contextRelPath), $phpContent);
        $count = count($steps);
        $this->line("  <fg=green>✓</> Context creado: <comment>{$contextRelPath}</comment> ({$count} steps)");

        // 6. Registrar en behat.php
        $this->registerInBehatConfig($module, $capability, $namespace, $contextClass);

        // 7. Formatear con Pint
        exec('vendor/bin/pint '.escapeshellarg(base_path($contextRelPath)).' --quiet 2>&1');

        // 8. Correr la feature
        $this->newLine();
        $this->line('  Corriendo feature...');
        $this->newLine();
        passthru("vendor/bin/behat --config=behat.php --suite={$module} {$featurePath} 2>&1");

        return self::SUCCESS;
    }

    // ── Naming ────────────────────────────────────────────────────────────────

    private function featureNameToContextClass(string $feature): string
    {
        // recepcion_muestras_biologicas → RecepcionMuestrasBiologicasContext
        return str_replace('_', '', ucwords($feature, '_')).'Context';
    }

    // ── Parseo del .feature ───────────────────────────────────────────────────

    /** @return array<int, array{type: string, text: string, table: bool}> */
    private function parseSteps(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $steps = [];
        $seen = [];
        $inEsquema = false;
        $inEjemplos = false;
        $lastPrimary = 'Given';

        $allKeywords = implode('|', array_merge(
            array_map('preg_quote', array_keys(self::PRIMARY_KEYWORDS), array_fill(0, count(self::PRIMARY_KEYWORDS), '/')),
            array_map('preg_quote', self::SECONDARY_KEYWORDS, array_fill(0, count(self::SECONDARY_KEYWORDS), '/'))
        ));

        for ($i = 0, $total = count($lines); $i < $total; $i++) {
            $trimmed = trim($lines[$i]);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Cambios de sección
            if (preg_match('/^Esquema del escenario\s*:/u', $trimmed)) {
                $inEsquema = true;
                $inEjemplos = false;

                continue;
            }
            if (preg_match('/^(?:Escenario\s*:|Antecedentes\s*:)/u', $trimmed)) {
                $inEsquema = false;
                $inEjemplos = false;

                continue;
            }
            if (preg_match('/^Ejemplos\s*:/u', $trimmed)) {
                $inEjemplos = true;

                continue;
            }
            // Saltar filas de tabla y ejemplos
            if (str_starts_with($trimmed, '|') || $inEjemplos) {
                continue;
            }

            // Detectar keyword + texto del step
            if (! preg_match('/^('.$allKeywords.')\s+(.+)$/u', $trimmed, $m)) {
                continue;
            }

            [, $kw, $text] = $m;

            // Reemplazar <param> → :param en Esquemas
            if ($inEsquema) {
                $text = preg_replace('/<([^>]+)>/u', ':$1', $text);
            }

            // Resolver tipo Given/When/Then
            if (isset(self::PRIMARY_KEYWORDS[$kw])) {
                $lastPrimary = self::PRIMARY_KEYWORDS[$kw];
                $type = $lastPrimary;
            } else {
                $type = $lastPrimary; // Y/Pero/* heredan el último primario
            }

            // Detectar si la siguiente línea no vacía es tabla
            $hasTable = false;
            for ($j = $i + 1; $j < $total; $j++) {
                $next = trim($lines[$j]);
                if ($next === '') {
                    continue;
                }
                $hasTable = str_starts_with($next, '|');
                break;
            }

            // Deduplicar por (tipo, patrón)
            $key = "{$type}|{$text}";
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $steps[] = ['type' => $type, 'text' => $text, 'table' => $hasTable];
        }

        return $steps;
    }

    // ── Generación del archivo PHP ─────────────────────────────────────────────

    /** @param array<int, array{type: string, text: string, table: bool}> $steps */
    private function buildContextFile(string $namespace, string $contextClass, array $steps): string
    {
        $needsTable = array_filter($steps, fn ($s) => $s['table']) !== [];
        $types = array_unique(array_column($steps, 'type'));

        $modulePart = explode('\\', $namespace)[1]; // GestionPrestamosRecepciones
        $uses = ['use Behat\\Behat\\Tester\\Exception\\PendingException;'];

        if ($needsTable) {
            $uses[] = 'use Behat\\Gherkin\\Node\\TableNode;';
        }
        if (in_array('Given', $types, true)) {
            $uses[] = 'use Behat\\Step\\Given;';
        }
        if (in_array('Then', $types, true)) {
            $uses[] = 'use Behat\\Step\\Then;';
        }
        if (in_array('When', $types, true)) {
            $uses[] = 'use Behat\\Step\\When;';
        }
        $uses[] = "use Modules\\{$modulePart}\\Tests\\Behat\\Contexts\\BaseContext;";

        sort($uses);
        $usesBlock = implode("\n", $uses);

        $methodsBlock = implode("\n\n", array_map(fn ($s) => $this->buildMethod($s), $steps));

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            "namespace {$namespace};",
            '',
            $usesBlock,
            '',
            "final class {$contextClass} extends BaseContext",
            '{',
            $methodsBlock,
            '}',
            '',
        ]);
    }

    /** @param array{type: string, text: string, table: bool} $step */
    private function buildMethod(array $step): string
    {
        ['type' => $type, 'text' => $text, 'table' => $hasTable] = $step;

        $args = [];
        if (preg_match_all('/:([\w\x80-\xff]+)/u', $text, $m)) {
            foreach ($m[1] as $param) {
                $args[] = "string \${$param}";
            }
        }
        if ($hasTable) {
            $args[] = 'TableNode $table';
        }

        $name = $this->stepToMethodName($text);
        $argsList = $args ? '('.implode(', ', $args).')' : '()';

        return implode("\n", [
            "    #[{$type}('{$text}')]",
            "    public function {$name}{$argsList}: void",
            '    {',
            '        throw new PendingException;',
            '    }',
        ]);
    }

    private function stepToMethodName(string $text): string
    {
        // Quitar referencias :param del texto antes de nombrar el método
        $clean = preg_replace('/:([\w\x80-\xff]+)/u', '', $text);
        // Quitar caracteres que no sean letras, dígitos ni espacios
        $clean = preg_replace('/[^\pL\pN\s]/u', ' ', $clean);
        // Separar en palabras y hacer camelCase
        $words = array_filter(preg_split('/\s+/u', mb_strtolower(trim($clean))));

        return lcfirst(implode('', array_map(
            fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)).mb_substr($w, 1),
            $words
        )));
    }

    // ── Registro en behat.php ─────────────────────────────────────────────────

    private function registerInBehatConfig(
        string $module,
        string $capability,
        string $namespace,
        string $contextClass
    ): void {
        $path = base_path('behat.php');
        $content = file_get_contents($path);

        $fullClass = "{$namespace}\\{$contextClass}";

        if (str_contains($content, "'{$fullClass}'")) {
            $this->warn('Context ya estaba registrado en behat.php');

            return;
        }

        $capabilityComment = "// {$capability}";
        $newEntry = "\n                        '{$fullClass}',";

        if (str_contains($content, $capabilityComment)) {
            // Sección de capability ya existe — insertar después de la última entrada
            $commentPos = strpos($content, $capabilityComment);
            $searchFrom = $commentPos + strlen($capabilityComment);

            // Fin de la sección: próximo comentario o cierre del withContexts
            $nextComment = strpos($content, "\n                        //", $searchFrom);
            $closingParen = strpos($content, "\n                    )", $searchFrom);
            $sectionEnd = ($nextComment !== false && $nextComment < $closingParen)
                ? $nextComment
                : $closingParen;

            $section = substr($content, $commentPos, $sectionEnd - $commentPos);
            $lastCommaOffset = strrpos($section, ',');
            $insertAt = $commentPos + $lastCommaOffset + 1;
        } else {
            // Nueva capability — agregar al final del withContexts del módulo
            $suitePos = strpos($content, "Suite('{$module}')");
            $withContextsPos = strpos($content, '->withContexts(', $suitePos);
            $closingParen = strpos($content, "\n                    )", $withContextsPos);

            $beforeClosing = substr($content, 0, $closingParen);
            $insertAt = strrpos($beforeClosing, ',') + 1;

            $newEntry = "\n                        {$capabilityComment}{$newEntry}";
        }

        $content = substr($content, 0, $insertAt).$newEntry.substr($content, $insertAt);
        file_put_contents($path, $content);

        $this->line('  <fg=green>✓</> Registrado en behat.php');
    }
}
