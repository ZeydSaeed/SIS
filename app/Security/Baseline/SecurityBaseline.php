<?php

namespace App\Security\Baseline;

final class SecurityBaseline
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
     * @return list<array<string, mixed>>
     */
    public function rules(): array
    {
        $categories = $this->load()['categories'] ?? [];
        $rules = [];

        foreach ($categories as $categoryRules) {
            if (! is_array($categoryRules)) {
                continue;
            }

            foreach ($categoryRules as $rule) {
                if (is_array($rule)) {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rulesBySeverity(string $severity): array
    {
        return array_values(array_filter(
            $this->rules(),
            fn (array $rule): bool => ($rule['severity'] ?? '') === $severity,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = base_path('.cursor/security/SECURITY-BASELINE.json');
        if (! is_file($path)) {
            self::$data = [];

            return self::$data;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        self::$data = is_array($decoded) ? $decoded : [];

        return self::$data;
    }
}
