<?php

namespace App\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * R1.9 Option B — prevent reintroduction of quarantined legacy Attendance writers.
 */
final class QuarantinedLegacyWriterGuard
{
    /**
     * @return list<array{class: string, tokens: list<string>, allowlist: list<string>}>
     */
    public function definitions(): array
    {
        return [
            [
                'class' => 'App\\Services\\Attendance\\AttendanceBatchService',
                'tokens' => [
                    'AttendanceBatchService',
                    'App\\Services\\Attendance\\AttendanceBatchService',
                ],
                'allowlist' => [
                    'app/Services/Attendance/AttendanceBatchService.php',
                    'app/Architecture/QuarantinedLegacyWriterGuard.php',
                    'app/Architecture/ArchitectureValidator.php',
                    'app/Architecture/ArchitectureFitnessReport.php',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $violations = [];

        foreach ($this->definitions() as $definition) {
            foreach ($this->scanRoots() as $root) {
                if (! is_dir($root) && ! is_file($root)) {
                    continue;
                }

                foreach ($this->phpFiles($root) as $file) {
                    $relative = $this->relative($file);
                    $normalized = str_replace('\\', '/', $relative);

                    if ($this->isAllowlisted($normalized, $definition['allowlist'])) {
                        continue;
                    }

                    $content = file_get_contents($file->getPathname());
                    if ($content === false) {
                        continue;
                    }

                    foreach ($definition['tokens'] as $token) {
                        if ($this->containsToken($content, $token)) {
                            $violations[] = "{$relative}: [ARCH-LEGACY-001] Quarantined legacy writer "
                                ."{$definition['class']} must not be referenced — use Application/Attendance CQRS";
                            break;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param  list<string>  $allowlist
     */
    private function isAllowlisted(string $normalizedRelative, array $allowlist): bool
    {
        foreach ($allowlist as $allowed) {
            if ($normalizedRelative === str_replace('\\', '/', $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function containsToken(string $content, string $token): bool
    {
        return str_contains($content, $token);
    }

    /**
     * @return list<string>
     */
    private function scanRoots(): array
    {
        return [
            app_path(),
            base_path('routes'),
        ];
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFiles(string $path): array
    {
        if (is_file($path) && str_ends_with($path, '.php')) {
            return [new SplFileInfo($path)];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

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
