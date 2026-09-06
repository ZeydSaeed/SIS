<?php

namespace App\Architecture;

use SplFileInfo;

/**
 * Static analysis beyond `use` imports — catches app(), resolve(), FQCN ::class, facades.
 */
final class ArchitectureStaticAnalyzer
{
    public function __construct(
        private readonly ArchitectureBaseline $baseline = new ArchitectureBaseline,
    ) {}

    /** @var list<string> */
    private array $violations = [];

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $this->violations = [];

        foreach ($this->scanRoots() as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach ($this->phpFiles($root) as $file) {
                $this->analyzeFile($file);
            }
        }

        return $this->violations;
    }

    public function passes(): bool
    {
        return $this->validate() === [];
    }

    private function analyzeFile(SplFileInfo $file): void
    {
        $layer = $this->resolveLayer($file->getPathname());
        if ($layer === null) {
            return;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            return;
        }

        $relative = $this->relative($file);

        foreach ($this->baseline->forbiddenReferences()[$layer] ?? [] as $forbidden) {
            if ($this->containsReference($content, $forbidden)) {
                $this->violations[] = "{$relative}: [ARCH-001] {$layer} must not reference {$forbidden} (static analysis)";
            }
        }

        foreach ($this->baseline->bypassPatterns() as $pattern) {
            if (@preg_match('/'.$pattern.'/', $content) === 1) {
                $this->violations[] = "{$relative}: [ARCH-002] Bypass detected — forbidden dependency pattern ({$pattern})";
            }
        }

        if ($layer === 'Domain' || $layer === 'Application') {
            $this->scanTokens($relative, $content, $layer);
        }
    }

    private function scanTokens(string $relative, string $content, string $layer): void
    {
        $tokens = @token_get_all($content);
        if ($tokens === []) {
            return;
        }

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] !== T_STRING || ($tokens[$i - 1][0] ?? null) !== T_NS_SEPARATOR) {
                continue;
            }

            $fqcn = $this->collectFqcn($tokens, $i);
            if ($fqcn === null) {
                continue;
            }

            if ($this->isForbiddenFqcn($fqcn, $layer)) {
                $this->violations[] = "{$relative}: [ARCH-003] {$layer} must not reference {$fqcn} (FQCN/static)";
            }
        }

        if (preg_match_all('/\b(app|resolve)\s*\(\s*[\'"](App\\\\[^\'"]+)[\'"]\s*\)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $class = str_replace('\\\\', '\\', $match[2]);
                if ($this->isForbiddenFqcn($class, $layer)) {
                    $this->violations[] = "{$relative}: [ARCH-004] {$layer} must not resolve {$class} via {$match[1]}()";
                }
            }
        }
    }

    private function isForbiddenFqcn(string $fqcn, string $layer): bool
    {
        foreach ($this->baseline->forbiddenReferences()[$layer] ?? [] as $forbidden) {
            if (str_starts_with($fqcn, rtrim($forbidden, '\\')) || str_contains($fqcn, $forbidden)) {
                return true;
            }
        }

        if ($layer === 'Domain' && str_starts_with($fqcn, 'Illuminate\\')) {
            return true;
        }

        if ($layer === 'Application' && str_starts_with($fqcn, 'App\\Infrastructure\\')) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<mixed>  $tokens
     */
    private function collectFqcn(array $tokens, int $start): ?string
    {
        $parts = [];
        $i = $start;

        while ($i >= 0) {
            $token = $tokens[$i];
            if (is_array($token) && ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR)) {
                array_unshift($parts, is_array($token) ? $token[1] : $token);
                $i--;
            } else {
                break;
            }
        }

        while ($i < count($tokens)) {
            $token = $tokens[$i];
            if (is_array($token) && ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR)) {
                $parts[] = $token[1];
                $i++;
            } else {
                break;
            }
        }

        $fqcn = implode('', array_map(fn ($p) => str_replace('\\', '', $p) === '' ? '\\' : $p, $parts));

        return str_contains($fqcn, 'App\\') || str_contains($fqcn, 'Illuminate\\') ? $fqcn : null;
    }

    private function containsReference(string $content, string $needle): bool
    {
        if (str_contains($content, 'use '.$needle)) {
            return true;
        }

        if (str_contains($content, $needle.'::')) {
            return true;
        }

        if (str_contains($content, "'".$needle) || str_contains($content, '"'.$needle)) {
            return true;
        }

        return false;
    }

    private function resolveLayer(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);

        return match (true) {
            str_contains($normalized, '/app/Domain/') => 'Domain',
            str_contains($normalized, '/app/Application/') => 'Application',
            str_contains($normalized, '/app/Infrastructure/') => 'Infrastructure',
            str_contains($normalized, '/app/Http/') => 'Presentation',
            str_contains($normalized, '/app/Intelligence/') => 'IntelligenceRuntime',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function scanRoots(): array
    {
        return [
            app_path('Domain'),
            app_path('Application'),
            app_path('Http/Controllers'),
        ];
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
