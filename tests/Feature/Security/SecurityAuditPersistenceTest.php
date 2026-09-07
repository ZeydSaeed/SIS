<?php

namespace Tests\Feature\Security;

use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityAuditPersistenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function security_event_creates_database_record(): void
    {
        app(SchoolContext::class)->set(42);

        app(SecurityAuditLoggerInterface::class)->record(
            SecurityEventType::PolicyBlock,
            'security.test',
            'denied',
            null,
            'student:1',
            ['password' => 'secret-value', 'note' => 'safe'],
        );

        $this->assertDatabaseHas((new SecurityAuditLogRecord)->getTable(), [
            'event_id' => 'SEC_POLICY_BLOCK',
            'action' => 'security.test',
            'result' => 'denied',
            'school_id' => 42,
            'target_type' => 'student',
            'target_id' => '1',
        ]);

        $record = SecurityAuditLogRecord::query()->first();
        $this->assertNotNull($record?->correlation_id);
        $this->assertSame('[REDACTED]', $record?->metadata['password'] ?? null);
        $this->assertSame('safe', $record?->metadata['note'] ?? null);
    }
}
