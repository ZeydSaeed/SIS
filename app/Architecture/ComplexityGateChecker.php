<?php

namespace App\Architecture;

use SplFileInfo;

final class ComplexityGateChecker
{
    public function __construct(
        private readonly ArchitectureBaseline $baseline = new ArchitectureBaseline,
    ) {}

    /** @var list<string> */
    private array $violations = [];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $this->violations = [];
        $this->warnings = [];

        $path = app_path('Application');
        if (! is_dir($path)) {
            return [];
        }

        foreach ($this->phpFiles($path) as $file) {
            if (! str_ends_with($file->getFilename(), 'Handler.php')) {
                continue;
            }

            $this->analyzeHandler($file);
        }

        return $this->violations;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        if ($this->warnings === [] && $this->violations === []) {
            $this->validate();
        }

        return $this->warnings;
    }

    private function analyzeHandler(SplFileInfo $file): void
    {
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            return;
        }

        $relative = $this->relative($file);
        $lines = $this->countCodeLines($content);
        $cyclomatic = $this->cyclomaticComplexity($content);

        $config = $this->baseline->complexity();
        $warnLines = (int) ($config['handler_warn_lines'] ?? 50);
        $failLines = (int) ($config['handler_fail_lines'] ?? 150);
        $warnCc = (int) ($config['handler_warn_cyclomatic'] ?? 8);
        $failCc = (int) ($config['handler_fail_cyclomatic'] ?? 10);

        if ($lines >= $failLines) {
            $this->violations[] = "{$relative}: [ARCH-101] Handler complexity exceeded — {$lines} lines (max {$failLines}). Extract Domain Service / Specification / Strategy.";
        } elseif ($lines >= $warnLines) {
            $this->warnings[] = "{$relative}: [ARCH-100] Handler has {$lines} lines (warn ≥ {$warnLines})";
        }

        if ($cyclomatic > $failCc) {
            $this->violations[] = "{$relative}: [ARCH-103] Cyclomatic complexity {$cyclomatic} exceeds {$failCc}. Simplify handler branches.";
        } elseif ($cyclomatic > $warnCc) {
            $this->warnings[] = "{$relative}: [ARCH-102] Cyclomatic complexity {$cyclomatic} (warn > {$warnCc})";
        }
    }

    private function countCodeLines(string $content): int
    {
        $lines = 0;
        foreach (explode("\n", $content) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }
            $lines++;
        }

        return $lines;
    }

    private function cyclomaticComplexity(string $content): int
    {
        $tokens = @token_get_all($content);
        $complexity = 1;

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE, T_CATCH, T_CASE], true)) {
                $complexity++;
            }
        }

        $complexity += preg_match_all('/&&|\|\|/', $content) ?: 0;

        return $complexity;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function relative(SplFileInfo $file): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
    }
}
