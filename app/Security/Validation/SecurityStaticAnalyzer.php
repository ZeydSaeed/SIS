<?php

namespace App\Security\Validation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SecurityStaticAnalyzer
{
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

        $this->analyzeFormRequests();
        $this->analyzeApiRoutes();

        return $this->violations;
    }

    public function passes(): bool
    {
        return $this->validate() === [];
    }

    private function analyzeFile(SplFileInfo $file): void
    {
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            return;
        }

        $relative = $this->relative($file);

        if (str_contains($relative, 'SecurityStaticAnalyzer.php')) {
            return;
        }

        if (str_contains($relative, 'ComposerAuditGate.php')) {
            return;
        }

        if (str_contains($content, '@security-legacy-allowed')) {
            return;
        }

        foreach ([
            'eval(' => '[SEC-STATIC-001] eval() is forbidden',
            'exec(' => '[SEC-STATIC-002] exec() is forbidden',
            'shell_exec(' => '[SEC-STATIC-003] shell_exec() is forbidden',
            'system(' => '[SEC-STATIC-004] system() is forbidden',
            'passthru(' => '[SEC-STATIC-005] passthru() is forbidden',
            'proc_open(' => '[SEC-STATIC-006] proc_open() is forbidden',
        ] as $needle => $message) {
            if (str_contains($content, $needle)) {
                $this->violations[] = "{$relative}: {$message}";
            }
        }

        if (preg_match('/\bunserialize\s*\(\s*\$/', $content) === 1) {
            $this->violations[] = "{$relative}: [SEC-STATIC-007] unserialize() on variable input is forbidden";
        }

        if (preg_match('/DB::(statement|select|raw)\s*\(\s*\$/', $content) === 1) {
            $this->violations[] = "{$relative}: [SEC-DB-001] Possible raw SQL with variable input";
        }

        if (preg_match('/DB::(statement|select|raw)\s*\([^)]*["\'][^"\']*["\']\s*\.\s*\$/', $content) === 1) {
            $this->violations[] = "{$relative}: [SEC-DB-001] Possible SQL string concatenation with variable";
        }

        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE)\[[^\]]+\]\s*\./', $content) === 1) {
            $this->violations[] = "{$relative}: [SEC-DB-001] Possible SQL concatenation with superglobal input";
        }

        if (preg_match('/Log::(info|debug|warning|error)\([^;]*(password|access_token|refresh_token|api_key)/i', $content) === 1) {
            $this->violations[] = "{$relative}: [SEC-LOG-001] Possible secret logging detected";
        }
    }

    private function analyzeFormRequests(): void
    {
        $path = app_path('Http/Requests');
        if (! is_dir($path)) {
            return;
        }

        foreach ($this->phpFiles($path) as $file) {
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            if (str_contains($content, '@security-exception')) {
                continue;
            }

            if (preg_match('/function\s+authorize\s*\(\s*\)\s*:\s*bool\s*\{\s*return\s+true\s*;/', $content) === 1) {
                $this->violations[] = $this->relative($file).': [SEC-AUTHZ-002] FormRequest authorize() returns true without @security-exception';
            }
        }
    }

    private function analyzeApiRoutes(): void
    {
        $routesFile = base_path('routes/api.php');
        if (! is_file($routesFile)) {
            $this->violations[] = '[SEC-AUTH-001] routes/api.php missing';

            return;
        }

        $content = (string) file_get_contents($routesFile);
        $publicRoutes = config('security.public_api_routes', ['api.health']);

        if (! str_contains($content, 'auth:sanctum') && ! str_contains($content, "middleware(['auth")) {
            $this->violations[] = '[SEC-AUTH-001] Protected API routes must use auth:sanctum middleware group';
        }

        if (preg_match_all("/Route::(get|post|put|patch|delete|apiResource)\([^)]+\)->name\('([^']+)'/", $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $routeName = $match[2];
                if (in_array($routeName, $publicRoutes, true)) {
                    continue;
                }

                if (! str_contains($content, 'auth:sanctum')) {
                    $this->violations[] = "[SEC-AUTH-001] Route {$routeName} must be behind auth:sanctum middleware";
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function scanRoots(): array
    {
        return [
            app_path('Http/Controllers'),
            app_path('Application'),
            app_path('Infrastructure'),
            app_path('Intelligence'),
            app_path('Security'),
            app_path('Console/Commands'),
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
