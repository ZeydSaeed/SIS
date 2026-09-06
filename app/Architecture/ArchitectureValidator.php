<?php

namespace App\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureValidator
{
    public function __construct(
        private readonly ArchitectureDependencyGraph $graph = new ArchitectureDependencyGraph,
        private readonly ArchitectureStaticAnalyzer $staticAnalyzer = new ArchitectureStaticAnalyzer,
        private readonly ComplexityGateChecker $complexity = new ComplexityGateChecker,
        private readonly FeatureContractValidator $featureContract = new FeatureContractValidator,
        private readonly SecurityFitnessChecker $security = new SecurityFitnessChecker,
        private readonly IntelligenceGovernanceChecker $intelligence = new IntelligenceGovernanceChecker,
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

        $this->validateDomainLayer();
        $this->validateApplicationLayer();
        $this->validateControllers();
        $this->validateLegacyPaths();

        return array_values(array_unique([
            ...$this->violations,
            ...$this->graph->validate(),
            ...$this->staticAnalyzer->validate(),
            ...$this->complexity->validate(),
            ...$this->featureContract->validate(),
            ...$this->security->validate(),
            ...$this->intelligence->validate(),
        ]));
    }

    public function passes(): bool
    {
        return $this->validate() === [];
    }

    public function dependencyGraph(): ArchitectureDependencyGraph
    {
        return $this->graph;
    }

    public function complexityChecker(): ComplexityGateChecker
    {
        return $this->complexity;
    }

    public function featureContract(): FeatureContractValidator
    {
        return $this->featureContract;
    }

    public function baseline(): ArchitectureBaseline
    {
        return $this->baseline;
    }

    private function validateDomainLayer(): void
    {
        $domainPath = app_path('Domain');
        if (! is_dir($domainPath)) {
            return;
        }

        foreach ($this->phpFiles($domainPath) as $file) {
            $content = $this->read($file);
            if ($content === null) {
                continue;
            }

            $this->forbidImports($file, $content, [
                'Illuminate\\' => 'Domain must not import Illuminate',
                'App\\Application\\' => 'Domain must not import Application layer',
                'App\\Infrastructure\\' => 'Domain must not import Infrastructure layer',
                'App\\Http\\' => 'Domain must not import Http layer',
                'App\\Models\\' => 'Domain must not import Eloquent models',
                'App\\Intelligence\\' => 'Domain must not import Intelligence runtime',
            ]);

            if (preg_match('/\bDB::/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Domain must not use DB facade";
            }

            if (preg_match('/\bextends\s+Model\b/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Domain must not extend Eloquent Model";
            }
        }
    }

    private function validateApplicationLayer(): void
    {
        $path = app_path('Application');
        if (! is_dir($path)) {
            return;
        }

        foreach ($this->phpFiles($path) as $file) {
            $content = $this->read($file);
            if ($content === null) {
                continue;
            }

            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Contracts'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $this->forbidImports($file, $content, [
                'App\\Http\\' => 'Application must not import Http layer',
                'App\\Infrastructure\\' => 'Application must not import Infrastructure implementations — use ports',
                'App\\Models\\' => 'Application must not import Eloquent models — use repository ports',
                'App\\Intelligence\\Models\\' => 'Application must not import Intelligence models — use ports',
                'Illuminate\\Http\\Request' => 'Application must not depend on HTTP Request',
            ]);

            if (preg_match('/extends\s+Controller/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Application must not extend Controller";
            }

            if ($this->isHandler($file) && preg_match('/\bDB::(transaction|table|select|insert|update|delete|statement)\s*\(/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Handlers must use UnitOfWork/repositories, not DB facade";
            }

            if ($this->isHandler($file) && preg_match('/\buse Illuminate\\\\Support\\\\Facades\\\\DB\b/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Handlers must not import DB facade";
            }
        }
    }

    private function validateControllers(): void
    {
        $path = app_path('Http/Controllers');
        if (! is_dir($path)) {
            return;
        }

        foreach ($this->phpFiles($path) as $file) {
            $content = $this->read($file);
            if ($content === null) {
                continue;
            }

            if (preg_match('/\bDB::(transaction|table|select|insert|update|delete|statement)\s*\(/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Controller must not use DB directly — use Application handler";
            }

            if (preg_match('/\buse App\\\\Models\\\\/', $content) === 1
                && preg_match('/\b[A-Za-z0-9_]+::(create|update|destroy|query)\s*\(/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Controller must not call Eloquent directly — use Application layer";
            }

            if (preg_match('/\buse App\\\\Intelligence\\\\Models\\\\/', $content) === 1
                && preg_match('/\b[A-Za-z0-9_]+::(create|update|destroy|query|find)\s*\(/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Controller must not call Intelligence models directly — use Application handler";
            }
        }
    }

    private function validateLegacyPaths(): void
    {
        $legacyService = app_path('Services');
        if (! is_dir($legacyService)) {
            return;
        }

        foreach ($this->phpFiles($legacyService) as $file) {
            $content = $this->read($file);
            if ($content === null) {
                continue;
            }

            if (preg_match('/@architecture-legacy-allowed/', $content) === 1) {
                continue;
            }

            if (preg_match('/\bclass\s+\w+/', $content) === 1 && filemtime($file->getPathname()) > strtotime('-1 day')) {
                $this->violations[] = "{$this->relative($file)}: Do not add new business logic to app/Services — use Application/{Context}. Add @architecture-legacy-allowed if migrating.";
            }
        }
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private function forbidImports(SplFileInfo $file, string $content, array $patterns): void
    {
        foreach ($patterns as $prefix => $message) {
            $escaped = preg_quote($prefix, '/');
            if (preg_match('/\buse '.$escaped.'/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: {$message}";
            }
        }
    }

    private function isHandler(SplFileInfo $file): bool
    {
        return str_ends_with($file->getFilename(), 'Handler.php');
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

    private function read(SplFileInfo $file): ?string
    {
        $content = file_get_contents($file->getPathname());

        return $content === false ? null : $content;
    }

    private function relative(SplFileInfo $file): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
    }
}
