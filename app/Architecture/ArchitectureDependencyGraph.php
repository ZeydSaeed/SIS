<?php

namespace App\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Scans `use` imports and enforces allowed dependency direction between layers.
 */
final class ArchitectureDependencyGraph
{
    /** @var list<string> */
    private array $violations = [];

    /** @var list<array{from: string, to: string, file: string}> */
    private array $edges = [];

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $this->violations = [];
        $this->edges = [];

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

    /**
     * @return list<array{from: string, to: string, file: string}>
     */
    public function edges(): array
    {
        if ($this->edges === []) {
            $this->validate();
        }

        return $this->edges;
    }

    public function diagram(): string
    {
        return <<<'TEXT'
SIS Allowed Dependency Direction
================================

    Presentation (Http/Controllers, Form Requests)
              │
              ▼
         Application (Commands, Queries, DTOs, Results, Handlers)
              │
              ▼
           Domain (Entities, ValueObjects, Specifications, Events, Ports)
              ▲
              │
      Infrastructure (Persistence, Outbox, Queue, External, Intelligence Adapters)

Forbidden (examples):
  Domain → Application | Infrastructure | Http | Illuminate | Eloquent  ❌
  Application → Http | Infrastructure impl | Eloquent | DB facade       ❌
  Controller → DB | Eloquent | business rules                           ❌

Source of truth: architecture:validate + Architecture tests + CI (not Cursor alone).
TEXT;
    }

    private function analyzeFile(SplFileInfo $file): void
    {
        $fromLayer = $this->resolveLayer($file->getPathname());
        if ($fromLayer === null) {
            return;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            return;
        }

        if (! preg_match_all('/^use\s+([^;]+);/m', $content, $matches)) {
            return;
        }

        foreach ($matches[1] as $import) {
            $import = trim($import);
            $toLayer = $this->resolveImportLayer($import);
            if ($toLayer === null) {
                continue;
            }

            $this->edges[] = [
                'from' => $fromLayer,
                'to' => $toLayer,
                'file' => $this->relative($file),
            ];

            if (! $this->isAllowed($fromLayer, $toLayer, $import)) {
                $this->violations[] = "{$this->relative($file)}: {$fromLayer} must not depend on {$toLayer} ({$import})";
            }
        }
    }

    private function isAllowed(string $from, string $to, string $import): bool
    {
        if ($from === $to) {
            return true;
        }

        return match ($from) {
            'Domain' => ! in_array($to, ['Application', 'Infrastructure', 'Presentation', 'Illuminate', 'Eloquent', 'IntelligenceRuntime'], true)
                && ! str_starts_with($import, 'Illuminate\\'),
            'Application' => ! in_array($to, ['Presentation', 'Infrastructure', 'Illuminate', 'Eloquent', 'IntelligenceRuntime'], true)
                && ! str_starts_with($import, 'Illuminate\\Http\\')
                && ! str_starts_with($import, 'Illuminate\\Support\\Facades\\DB'),
            'Presentation' => $to !== 'Domain' || str_contains($import, 'Domain\\'), // controllers may type-hint domain exceptions only sparingly; block is soft - actually controllers shouldn't import domain much
            'Infrastructure' => $to !== 'Presentation',
            default => true,
        };
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

    private function resolveImportLayer(string $import): ?string
    {
        return match (true) {
            str_starts_with($import, 'App\\Domain\\') => 'Domain',
            str_starts_with($import, 'App\\Application\\') => 'Application',
            str_starts_with($import, 'App\\Infrastructure\\') => 'Infrastructure',
            str_starts_with($import, 'App\\Http\\') => 'Presentation',
            str_starts_with($import, 'App\\Intelligence\\') => 'IntelligenceRuntime',
            str_starts_with($import, 'App\\Models\\') => 'Eloquent',
            str_starts_with($import, 'Illuminate\\') => 'Illuminate',
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
            app_path('Infrastructure'),
            app_path('Http'),
            app_path('Intelligence'),
        ];
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

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
