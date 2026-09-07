<?php

namespace App\Security\Validation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SecretScanner
{
    /** @var list<string> */
    private const PATTERNS = [
        '/AKIA[0-9A-Z]{16}/' => 'Possible AWS access key',
        '/-----BEGIN (RSA |EC )?PRIVATE KEY-----/' => 'Private key material',
        '/(?i)(api[_-]?key|secret[_-]?key|password)\s*=\s*[\'"][^\'"\s]{8,}[\'"]/' => 'Possible hardcoded secret assignment',
    ];

    /** @var list<string> */
    private const SCAN_ROOTS = [
        'app',
        'config',
        'routes',
        'database',
        '.env.example',
    ];

    /** @var list<string> */
    private const IGNORE_PATH_FRAGMENTS = [
        'vendor',
        'node_modules',
        'SecretScanner.php',
        'SecurityStaticAnalyzerTest.php',
        'tests',
    ];

    /**
     * @return list<string>
     */
    public function scan(): array
    {
        $violations = [];

        foreach (self::SCAN_ROOTS as $root) {
            $path = base_path($root);
            if (is_file($path)) {
                $this->scanFile(new SplFileInfo($path), $violations);

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $this->scanFile($file, $violations);
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param  list<string>  $violations
     */
    private function scanFile(SplFileInfo $file, array &$violations): void
    {
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

        foreach (self::IGNORE_PATH_FRAGMENTS as $fragment) {
            if (str_contains($relative, $fragment)) {
                return;
            }
        }

        if (! in_array($file->getExtension(), ['php', 'env', 'example', 'yaml', 'yml', 'json', 'md'], true)) {
            return;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false || str_contains($content, '@security-secret-allowed')) {
            return;
        }

        foreach (self::PATTERNS as $pattern => $message) {
            if (preg_match($pattern, $content) === 1) {
                $violations[] = "[SEC-SECRET-001] {$relative}: {$message}";
            }
        }
    }
}
