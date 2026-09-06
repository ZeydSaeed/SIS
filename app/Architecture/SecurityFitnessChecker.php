<?php

namespace App\Architecture;

final class SecurityFitnessChecker
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

        $config = $this->baseline->security();

        $middleware = (string) ($config['school_context_middleware'] ?? '');
        if ($middleware !== '') {
            $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));
            if (! str_contains($bootstrap, class_basename($middleware))) {
                $this->violations[] = '[SEC-001] SchoolContextMiddleware must be registered in bootstrap/app.php web stack';
            }
        }

        $rlsMigration = (string) ($config['rls_migration'] ?? '');
        if ($rlsMigration !== '' && ! is_file(base_path($rlsMigration))) {
            $this->violations[] = "[SEC-002] RLS migration missing: {$rlsMigration}";
        }

        if ($rlsMigration !== '' && is_file(base_path($rlsMigration))) {
            $content = (string) file_get_contents(base_path($rlsMigration));
            foreach ($config['rls_tables'] ?? [] as $table) {
                if (! str_contains($content, $table)) {
                    $this->violations[] = "[SEC-003] RLS migration must enable RLS on {$table}";
                }
            }
        }

        if (! is_file(app_path('Security/Middleware/SchoolContextMiddleware.php'))) {
            $this->violations[] = '[SEC-004] SchoolContextMiddleware class missing';
        }

        return $this->violations;
    }
}
