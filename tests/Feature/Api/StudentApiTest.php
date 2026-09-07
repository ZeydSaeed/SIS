<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;

    #[Test]
    public function can_create_list_search_show_and_update_student(): void
    {
        $this->actingAsStudentManager();

        $create = $this->postJson('/api/v1/students', [
            'first_name' => 'Ali',
            'middle_name' => 'Hassan',
            'last_name' => 'Karim',
            'gender' => 1,
            'birth_date' => '2010-05-15',
            'national_id' => 'NAT-1001',
            'student_code' => 'STU-API-001',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.student_code', 'STU-API-001');

        $studentId = (int) $create->json('data.id');

        $this->getJson("/api/v1/students/{$studentId}")
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Ali Hassan Karim')
            ->assertJsonPath('data.national_id', 'NAT-1001');

        $this->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student_code', 'STU-API-001');

        $this->getJson('/api/v1/students/search?q=Ali')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/students/{$studentId}", [
            'first_name' => 'Ali',
            'middle_name' => 'Hassan',
            'last_name' => 'Updated',
            'gender' => 1,
            'birth_date' => '2010-05-15',
            'national_id' => 'NAT-1001',
        ])->assertOk();

        $this->getJson("/api/v1/students/{$studentId}")
            ->assertJsonPath('data.last_name', 'Updated')
            ->assertJsonPath('data.full_name', 'Ali Hassan Updated');
    }

    #[Test]
    public function create_student_auto_generates_code_when_omitted(): void
    {
        $this->actingAsStudentManager();

        $response = $this->postJson('/api/v1/students', [
            'first_name' => 'Sara',
            'last_name' => 'Adil',
            'gender' => 2,
            'birth_date' => '2011-03-20',
        ]);

        $response->assertCreated();
        $this->assertMatchesRegularExpression('/^STU-\d{6}$/', (string) $response->json('data.student_code'));
    }

    #[Test]
    public function duplicate_student_code_returns_domain_error(): void
    {
        $this->actingAsStudentManager();

        $payload = [
            'first_name' => 'Omar',
            'last_name' => 'Saleh',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'student_code' => 'STU-DUP-001',
        ];

        $this->postJson('/api/v1/students', $payload)->assertCreated();

        $this->postJson('/api/v1/students', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'student.code_exists');
    }

    #[Test]
    public function show_missing_student_returns_not_found(): void
    {
        $this->actingAsStudentManager();

        $this->getJson('/api/v1/students/99999')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'student.not_found');
    }

    #[Test]
    public function create_student_honors_idempotency_key(): void
    {
        $this->actingAsStudentManager();

        $payload = [
            'first_name' => 'Noor',
            'last_name' => 'Fahad',
            'gender' => 2,
            'birth_date' => '2012-07-07',
            'student_code' => 'STU-IDEM-001',
        ];

        $first = $this->withHeader('X-Idempotency-Key', 'student-create-idem-1')
            ->postJson('/api/v1/students', $payload);

        $second = $this->withHeader('X-Idempotency-Key', 'student-create-idem-1')
            ->postJson('/api/v1/students', $payload);

        $first->assertCreated();
        $second->assertCreated()
            ->assertJsonPath('meta.from_idempotency_cache', true)
            ->assertJsonPath('data.id', $first->json('data.id'));
    }
}
