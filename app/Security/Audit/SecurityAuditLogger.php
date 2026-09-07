<?php

namespace App\Security\Audit;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class SecurityAuditLogger implements SecurityAuditLoggerInterface
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        SecurityEventType $event,
        string $action,
        string $result,
        ?User $actor = null,
        ?string $target = null,
        array $context = [],
    ): void {
        $sanitizedContext = $this->sanitizeContext($context);
        $correlationId = CorrelationContext::id();
        $schoolId = $this->schoolContext->id();
        [$targetType, $targetId] = $this->parseTarget($target);

        $payload = [
            'event_id' => $event->value,
            'timestamp' => now()->toIso8601String(),
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'action' => $action,
            'target' => $target,
            'result' => $result,
            'correlation_id' => $correlationId,
            'school_id' => $schoolId,
            'context' => $sanitizedContext,
        ];

        Log::channel(config('security.audit_channel', 'stack'))->info('security.audit', $payload);

        if ($this->auditTableAvailable()) {
            SecurityAuditLogRecord::query()->create([
                'event_id' => $event->value,
                'occurred_at' => now(),
                'actor_id' => $actor?->id,
                'actor_type' => $actor !== null ? 'user' : 'system',
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'school_id' => $schoolId,
                'result' => $result,
                'correlation_id' => $correlationId,
                'metadata' => $sanitizedContext,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseTarget(?string $target): array
    {
        if ($target === null || $target === '') {
            return [null, null];
        }

        if (str_contains($target, ':')) {
            [$type, $id] = explode(':', $target, 2);

            return [$type, $id];
        }

        return ['resource', $target];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $forbidden = [
            'password', 'token', 'secret', 'api_key', 'authorization',
            'access_token', 'refresh_token', 'cookie', 'session_secret', 'private_key',
        ];

        foreach ($context as $key => $value) {
            if (is_string($key) && collect($forbidden)->contains(fn (string $needle): bool => str_contains(strtolower($key), $needle))) {
                $context[$key] = '[REDACTED]';
            }
        }

        return $context;
    }

    private function auditTableAvailable(): bool
    {
        try {
            return Schema::hasTable(
                SchemaHelper::qualified('security', 'security_audit_logs'),
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
