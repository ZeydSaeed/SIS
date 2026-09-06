<?php

namespace App\Architecture;

final class ArchitectureBaseline
{
    /** @var array<string, mixed>|null */
    private static ?array $data = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->load();
    }

    public function version(): string
    {
        return (string) ($this->load()['version'] ?? '0');
    }

    /**
     * @return array<string, mixed>
     */
    public function complexity(): array
    {
        /** @var array<string, mixed> */
        return $this->load()['complexity'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function featureContract(): array
    {
        /** @var array<string, mixed> */
        return $this->load()['feature_contract'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function security(): array
    {
        /** @var array<string, mixed> */
        return $this->load()['security'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function intelligenceGovernance(): array
    {
        /** @var array<string, mixed> */
        return $this->load()['intelligence_governance'] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function forbiddenReferences(): array
    {
        /** @var array<string, list<string>> */
        return $this->load()['forbidden_references'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function bypassPatterns(): array
    {
        /** @var list<string> */
        return $this->load()['bypass_patterns'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = base_path('.cursor/architecture/ARCHITECTURE-BASELINE.json');
        if (! is_file($path)) {
            self::$data = [];

            return self::$data;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        self::$data = is_array($decoded) ? $decoded : [];

        return self::$data;
    }
}
