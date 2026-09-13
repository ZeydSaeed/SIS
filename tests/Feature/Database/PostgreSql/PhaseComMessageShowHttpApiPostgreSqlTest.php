<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Communication\ValueObjects\MessageStatus;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComMessageShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_message(): void
    {
        $schoolId = $this->createSchool('SCH-COM-MS1', 'COM Show Message');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $messageId = (int) $this->postJson('/api/v1/communication/messages', [
            'recipient_type' => 'student',
            'recipient_id' => 42,
            'channel' => NotificationChannel::Email,
            'subject' => 'Show subject',
            'body' => 'Show body',
        ], ['X-Idempotency-Key' => 'com-show-msg'])->json('data.message_id');

        $this->getJson('/api/v1/communication/messages/'.$messageId)
            ->assertOk()
            ->assertJsonPath('data.id', $messageId)
            ->assertJsonPath('data.recipient_type', 'student')
            ->assertJsonPath('data.recipient_id', 42)
            ->assertJsonPath('data.channel', NotificationChannel::Email)
            ->assertJsonPath('data.subject', 'Show subject')
            ->assertJsonPath('data.body', 'Show body')
            ->assertJsonPath('data.status', MessageStatus::Queued)
            ->assertJsonPath('data.sent_at', null);
    }
}
