<?php

namespace App\Architecture;

final class FeatureContractValidator
{
    public function __construct(
        private readonly ArchitectureBaseline $baseline = new ArchitectureBaseline,
    ) {}

    /** @var list<string> */
    private array $violations = [];

    /**
     * @return list<string>
     */
    public function validate(?string $context = null): array
    {
        $this->violations = [];

        $applicationPath = app_path('Application');
        if (! is_dir($applicationPath)) {
            return [];
        }

        $contexts = $context !== null
            ? [$context]
            : array_map(basename(...), array_filter(glob($applicationPath.'/*'), 'is_dir'));

        foreach ($contexts as $ctx) {
            $this->validateContext($ctx);
        }

        return $this->violations;
    }

    private function validateContext(string $context): void
    {
        $commandsPath = app_path("Application/{$context}/Commands");
        if (! is_dir($commandsPath)) {
            return;
        }

        $contract = $this->baseline->featureContract()['write_command'] ?? [];
        $sensitivePatterns = $contract['sensitive_name_patterns'] ?? [];
        $requireIdempotency = (bool) ($contract['require_idempotency_key_when_sensitive'] ?? true);
        $requireResult = (bool) ($contract['required_result_class'] ?? true);

        foreach (glob($commandsPath.'/*Command.php') ?: [] as $commandFile) {
            $baseName = basename($commandFile, 'Command.php');
            $handlerFile = $commandsPath.'/'.$baseName.'Handler.php';

            if (! is_file($handlerFile)) {
                $this->violations[] = "Application/{$context}/Commands/{$baseName}Command.php: [ARCH-201] Missing handler {$baseName}Handler.php";
            }

            if ($requireResult && ! $this->exemptFromResult($baseName)) {
                $resultFile = app_path("Application/{$context}/Results/{$baseName}Result.php");
                if (! is_file($resultFile)) {
                    $this->violations[] = "Application/{$context}/Commands/{$baseName}Command.php: [ARCH-202] Write command requires {$baseName}Result.php";
                }
            }

            if ($requireIdempotency && $this->isSensitive($baseName, $sensitivePatterns)) {
                $content = (string) file_get_contents($commandFile);
                if (! str_contains($content, 'idempotencyKey')) {
                    $this->violations[] = "Application/{$context}/Commands/{$baseName}Command.php: [ARCH-203] Sensitive command must accept idempotencyKey";
                }
            }
        }

        $queriesPath = app_path("Application/{$context}/Queries");
        if (is_dir($queriesPath)) {
            foreach (glob($queriesPath.'/*Query.php') ?: [] as $queryFile) {
                $baseName = basename($queryFile, 'Query.php');
                $handlerFile = $queriesPath.'/'.$baseName.'Handler.php';
                if (! is_file($handlerFile)) {
                    $this->violations[] = "Application/{$context}/Queries/{$baseName}Query.php: [ARCH-204] Missing handler {$baseName}Handler.php";
                }
            }
        }
    }

    /**
     * @param  list<string>  $patterns
     */
    private function isSensitive(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function exemptFromResult(string $baseName): bool
    {
        return str_starts_with($baseName, 'Reject')
            || str_starts_with($baseName, 'Delete')
            || str_starts_with($baseName, 'Cancel');
    }

    /**
     * @return array<string, array{required: list<string>, conditional: list<string>}>
     */
    public function contractFor(string $context): array
    {
        return [
            'required' => [
                'Command + Handler + Result (writes)',
                'Query + Handler + DTO (reads)',
                'Unit tests',
                'architecture:validate --fitness',
            ],
            'conditional' => [
                'Repository port (when persistence is non-trivial)',
                'Domain Event + Outbox (when side effects)',
                'Idempotency key (sensitive commands)',
                'Policy (HTTP endpoints)',
                'Specification (complex eligibility rules)',
            ],
        ];
    }
}
