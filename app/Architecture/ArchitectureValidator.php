<?php

namespace App\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureValidator
{
    /** @var list<string> */
    private array $violations = [];

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $this->violations = [];

        $this->validateDomainLayer();
        $this->validateControllers();
        $this->validateApplicationLayer();

        return $this->violations;
    }

    public function passes(): bool
    {
        return $this->validate() === [];
    }

    private function validateDomainLayer(): void
    {
        $domainPath = app_path('Domain');
        if (! is_dir($domainPath)) {
            return;
        }

        foreach ($this->phpFiles($domainPath) as $file) {
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            if (preg_match('/\buse Illuminate\\\\/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Domain must not import Illuminate";
            }

            if (preg_match('/\buse App\\\\Models\\\\/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Domain must not import Eloquent models";
            }

            if (preg_match('/\bDB::/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Domain must not use DB facade";
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
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Contracts'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (preg_match('/extends\s+Controller/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Application must not extend Controller";
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
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Intelligence'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            if (preg_match('/\bDB::(transaction|table|select|insert|update|delete|statement)\s*\(/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Controller must not use DB directly — use Application handler";
            }

            if (preg_match('/\b[A-Za-z0-9_]+::(create|update|destroy|query)\s*\(/', $content) === 1
                && preg_match('/\buse App\\\\Models\\\\/', $content) === 1) {
                $this->violations[] = "{$this->relative($file)}: Controller must not call Eloquent directly — use Application layer";
            }
        }
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
