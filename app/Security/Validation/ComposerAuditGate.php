<?php

namespace App\Security\Validation;

use Symfony\Component\Process\Process;

final class ComposerAuditGate
{
    /**
     * @return array{passed: bool, exit_code: int, output: string, blocking_count: int, severities: list<string>}
     */
    public function evaluate(?string $auditJson = null, ?int $exitCode = null): array
    {
        $policy = $this->policy();
        $blockSeverities = array_map('strtolower', $policy['block_severities'] ?? ['critical', 'high']);

        if ($auditJson === null) {
            $process = new Process(['composer', 'audit', '--no-dev', '--format=json']);
            $process->run();
            $auditJson = $process->getOutput();
            $exitCode = $process->getExitCode();
        }

        $exitCode ??= 0;
        $blockingCount = 0;
        $severities = [];

        $decoded = json_decode($auditJson, true);
        if (is_array($decoded)) {
            foreach ($decoded['advisories'] ?? [] as $packageAdvisories) {
                if (! is_array($packageAdvisories)) {
                    continue;
                }

                foreach ($packageAdvisories as $advisory) {
                    if (! is_array($advisory)) {
                        continue;
                    }

                    $severity = strtolower((string) ($advisory['severity'] ?? 'unknown'));
                    $severities[] = $severity;

                    if (in_array($severity, $blockSeverities, true)) {
                        $blockingCount++;
                    }
                }
            }
        }

        $passed = $exitCode === 0 && $blockingCount === 0;

        return [
            'passed' => $passed,
            'exit_code' => $exitCode,
            'output' => $auditJson,
            'blocking_count' => $blockingCount,
            'severities' => array_values(array_unique($severities)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function policy(): array
    {
        $path = base_path('.cursor/security/COMPOSER-AUDIT-POLICY.json');
        if (! is_file($path)) {
            return [
                'block_severities' => ['critical', 'high'],
                'command' => 'composer audit --no-dev --format=json',
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
