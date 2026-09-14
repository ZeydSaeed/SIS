<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Communication\ValueObjects\MessageStatus;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComMessageCancelRequeueHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_cancel_queued_message_and_requeue_failed(): void
    {
        $schoolId = $this->createSchool('SCH-MSG-CR-1', 'Message Cancel Requeue');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $messageId = (int) $this->postJson('/api/v1/communication/messages', [
            'recipient_type' => 'student',
            'recipient_id' => 9,
            'channel' => NotificationChannel::Email,
            'body' => 'Cancel me',
        ], ['X-Idempotency-Key' => 'msg-cr-q'])->json('data.message_id');

        $this->postJson('/api/v1/communication/messages/'.$messageId.'/cancel', [], [
            'X-Idempotency-Key' => 'msg-cr-cancel',
        ])
            ->assertOk()
            ->assertJsonPath('data.message_id', $messageId);

        $this->getJson('/api/v1/communication/messages/'.$messageId)
            ->assertOk()
            ->assertJsonPath('data.status', MessageStatus::Failed);

        $this->postJson('/api/v1/communication/messages/'.$messageId.'/requeue', [], [
            'X-Idempotency-Key' => 'msg-cr-requeue',
        ])
            ->assertOk()
            ->assertJsonPath('data.message_id', $messageId);

        $this->getJson('/api/v1/communication/messages/'.$messageId)
            ->assertOk()
            ->assertJsonPath('data.status', MessageStatus::Queued)
            ->assertJsonPath('data.sent_at', null);
    }
}
