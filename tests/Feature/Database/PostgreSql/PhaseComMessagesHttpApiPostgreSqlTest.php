<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Communication\ValueObjects\MessageStatus;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComMessagesHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function messages_table_has_force_rls_and_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'communication' AND table_name = 'messages'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);

        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'communication' AND c.relname = 'messages'
        ");
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function manager_can_queue_and_list_messages(): void
    {
        $schoolId = $this->createSchool('SCH-MSG-1', 'Messages 1');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/communication/messages', [
            'recipient_type' => 'student',
            'recipient_id' => 42,
            'channel' => NotificationChannel::Email,
            'subject' => 'Hello',
            'body' => 'Queued body',
        ], ['X-Idempotency-Key' => 'msg-q-1'])
            ->assertCreated();

        $messageId = (int) $create->json('data.message_id');

        $this->postJson('/api/v1/communication/messages', [
            'recipient_type' => 'student',
            'recipient_id' => 42,
            'channel' => NotificationChannel::Email,
            'subject' => 'Hello',
            'body' => 'Queued body',
        ], ['X-Idempotency-Key' => 'msg-q-1'])
            ->assertOk()
            ->assertJsonPath('data.message_id', $messageId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/communication/messages?recipient_type=student&recipient_id=42')
            ->assertOk()
            ->assertJsonPath('data.0.id', $messageId)
            ->assertJsonPath('data.0.status', MessageStatus::Queued)
            ->assertJsonPath('data.0.sent_at', null);

        $this->assertDatabaseHas(SchemaHelper::qualified('communication', 'messages'), [
            'id' => $messageId,
            'school_id' => $schoolId,
            'status' => MessageStatus::Queued,
        ]);
    }

    #[Test]
    public function viewer_cannot_queue_message(): void
    {
        $schoolId = $this->createSchool('SCH-MSG-2', 'Messages 2');
        $this->actingAsCommunicationViewerForSchool($schoolId);

        $this->postJson('/api/v1/communication/messages', [
            'recipient_type' => 'teacher',
            'recipient_id' => 1,
            'channel' => NotificationChannel::Sms,
            'body' => 'x',
        ], ['X-Idempotency-Key' => 'msg-deny'])
            ->assertForbidden();
    }
}
