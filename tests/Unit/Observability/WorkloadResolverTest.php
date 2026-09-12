<?php

namespace Tests\Unit\Observability;

use App\Observability\WorkloadResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WorkloadResolverTest extends TestCase
{
    #[Test]
    #[DataProvider('routeWorkloadProvider')]
    public function resolves_exact_and_prefix_workloads(?string $route, string $expected): void
    {
        $this->assertSame($expected, WorkloadResolver::forRoute($route));
    }

    /**
     * @return array<string, array{0:?string, 1:string}>
     */
    public static function routeWorkloadProvider(): array
    {
        return [
            'null' => [null, 'unknown'],
            'empty' => ['', 'unknown'],
            'student exact' => ['api.students.search', 'student_search'],
            'health exact' => ['api.health', 'health'],
            'finance prefix' => ['api.finance.fee-types.index', 'finance_oltp'],
            'workflow cancel' => ['api.workflow.approval-requests.cancel', 'workflow_oltp'],
            'communication' => ['api.communication.messages.store', 'communication_oltp'],
            'documents' => ['api.documents.index', 'documents_oltp'],
            'transfers' => ['api.transfers.requests.approve', 'transfers_oltp'],
            'teachers' => ['api.teachers.qualifications.index', 'teachers_oltp'],
            'promotion' => ['api.promotion.records.store', 'promotion_oltp'],
            'attendance' => ['api.attendance.sessions.index', 'attendance_oltp'],
            'results' => ['api.results.term.show', 'results_oltp'],
            'portal' => ['api.portal.results.gpa.show', 'portal_results'],
            'exam sessions' => ['api.exam_sessions.grades', 'exams_oltp'],
            'grades' => ['api.grades.store', 'exams_oltp'],
            'enrollment cancel' => ['api.enrollments.cancel', 'enrollment_oltp'],
            'unknown route' => ['api.security.admin_probe', 'general_api'],
        ];
    }
}
