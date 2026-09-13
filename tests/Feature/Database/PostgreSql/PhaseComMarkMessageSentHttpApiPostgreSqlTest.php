<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Communication\ValueObjects\MessageStatus;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComMarkMessageSentHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_mark_queued_message_sent_via_local_outbound(): void
    {
        $schoolId = $this->createSchool('SCH-MSG-SENT-1', 'Messages Sent 1');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $messageId = (int) $this->postJson('/api/v1/communication/messages', [
            'recipient_type' => 'student',
            'recipient_id' => 7,
            'channel' => NotificationChannel::Email,
            'body' => 'Ready to send',
        ], ['X-Idempotency-Key' => 'msg-sent-q'])->json('data.message_id');

        $this->postJson('/api/v1/communication/messages/'.$messageId.'/mark-sent', [], [
            'X-Idempotency-Key' => 'msg-sent-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.message_id', $messageId)
            ->assertJsonPath('data.from_idempotency', false);

        $this->postJson('/api/v1/communication/messages/'.$messageId.'/mark-sent', [], [
            'X-Idempotency-Key' => 'msg-sent-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/communication/messages?message_status='.MessageStatus::Sent)
            ->assertOk()
            ->assertJsonPath('data.0.id', $messageId)
            ->assertJsonPath('data.0.status', MessageStatus::Sent);

        $row = DB::table(SchemaHelper::qualified('communication', 'messages'))
            ->where('id', $messageId)
            ->first(['status', 'sent_at']);
        $this->assertSame(MessageStatus::Sent, (int) $row->status);
        $this->assertNotNull($row->sent_at);
    }

    #[Test]
    public function re_mark_without_idempotency_rejects_when_already_sent(): void
    {
        $schoolId = $this->createSchool('SCH-MSG-SENT-2', 'Messages Sent 2');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $messageId = (int) $this->postJson('/api/v1/communication/messages', [
            'recipient_type' => 'teacher',
            'recipient_id' => 3,
            'channel' => NotificationChannel::InApp,
            'body' => 'Once',
        ], ['X-Idempotency-Key' => 'msg-sent-once-q'])->json('data.message_id');

        $this->postJson('/api/v1/communication/messages/'.$messageId.'/mark-sent', [], [
            'X-Idempotency-Key' => 'msg-sent-once-a',
        ])->assertOk();

        $this->postJson('/api/v1/communication/messages/'.$messageId.'/mark-sent', [], [
            'X-Idempotency-Key' => 'msg-sent-once-b',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'communication.message_not_queued');
    }

    #[Test]
    public function viewer_cannot_mark_message_sent(): void
    {
        $schoolId = $this->createSchool('SCH-MSG-SENT-3', 'Messages Sent 3');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $messageId = (int) $this->postJson('/api/v1/communication/messages', [
            'recipient_type' => 'user',
            'recipient_id' => 1,
            'channel' => NotificationChannel::Sms,
            'body' => 'x',
        ], ['X-Idempotency-Key' => 'msg-sent-deny-q'])->json('data.message_id');

        $this->actingAsCommunicationViewerForSchool($schoolId);

        $this->postJson('/api/v1/communication/messages/'.$messageId.'/mark-sent', [], [
            'X-Idempotency-Key' => 'msg-sent-deny',
        ])->assertForbidden();
    }
}
