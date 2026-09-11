<?php

namespace App\Infrastructure\Persistence\Outbox;

use App\Application\Contracts\OutboxRepository;
use App\Domain\Attendance\Events\AttendanceCorrected;
use App\Domain\Attendance\Events\AttendanceSessionCancelled;
use App\Domain\Attendance\Events\AttendanceSessionClosed;
use App\Domain\Attendance\Events\AttendanceSessionCreated;
use App\Domain\Attendance\Events\SectionAttendanceMarked;
use App\Domain\Enrollment\Events\EnrollmentCancelled;
use App\Domain\Enrollment\Events\EnrollmentPlacementUpdated;
use App\Domain\Enrollment\Events\StudentEnrolled;
use App\Domain\Exams\Events\ExamCancelled;
use App\Domain\Exams\Events\ExamCreated;
use App\Domain\Exams\Events\ExamEnrollmentCancelled;
use App\Domain\Exams\Events\ExamEnrollmentCreated;
use App\Domain\Exams\Events\ExamEnrollmentUpdated;
use App\Domain\Exams\Events\ExamSessionCancelled;
use App\Domain\Exams\Events\ExamSessionClosed;
use App\Domain\Exams\Events\ExamSessionCreated;
use App\Domain\Exams\Events\ExamSessionOpened;
use App\Domain\Exams\Events\ExamSessionUpdated;
use App\Domain\Exams\Events\ExamUpdated;
use App\Domain\Exams\Events\StudentGradeCorrected;
use App\Domain\Exams\Events\StudentGradeEntered;
use App\Domain\Exams\Events\StudentGradeFinalized;
use App\Domain\Exams\Events\StudentGradeVoided;
use App\Domain\Graduation\Events\AwardIssued;
use App\Domain\Graduation\Events\AwardRevoked;
use App\Domain\Graduation\Events\CompletionEvaluated;
use App\Domain\Graduation\Events\CompletionOutcomeCreated;
use App\Domain\Graduation\Events\GraduationApproved;
use App\Domain\Shared\DomainEvent;
use App\Infrastructure\Persistence\Eloquent\OutboxMessageRecord;
use App\Intelligence\Support\CorrelationContext;

final class EloquentOutboxRepository implements OutboxRepository
{
    public function stage(DomainEvent $event, ?string $correlationId = null): void
    {
        OutboxMessageRecord::query()->create([
            'event_type' => $event::class,
            'payload' => $event->payload(),
            'correlation_id' => $correlationId ?? CorrelationContext::id(),
            'occurred_at' => $event->occurredAt(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<OutboxMessageRecord>
     */
    public function fetchUnprocessed(int $limit = 50): array
    {
        return OutboxMessageRecord::query()
            ->whereNull('processed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function markProcessed(int $id): void
    {
        OutboxMessageRecord::query()
            ->whereKey($id)
            ->update(['processed_at' => now()]);
    }

    public function incrementAttempts(int $id): void
    {
        OutboxMessageRecord::query()
            ->whereKey($id)
            ->increment('attempts');
    }

    public function rehydrateEvent(string $eventType, array $payload): ?DomainEvent
    {
        return match ($eventType) {
            StudentEnrolled::class => new StudentEnrolled(
                enrollmentId: (int) $payload['enrollment_id'],
                studentId: (int) $payload['student_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                classId: (int) $payload['class_id'],
                sectionId: (int) $payload['section_id'],
                enrollmentNumber: (string) $payload['enrollment_number'],
                enrolledBy: isset($payload['enrolled_by']) ? (int) $payload['enrolled_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            EnrollmentCancelled::class => new EnrollmentCancelled(
                enrollmentId: (int) $payload['enrollment_id'],
                studentId: (int) $payload['student_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                effectiveTo: (string) $payload['effective_to'],
                cancelledBy: isset($payload['cancelled_by']) ? (int) $payload['cancelled_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            EnrollmentPlacementUpdated::class => new EnrollmentPlacementUpdated(
                enrollmentId: (int) $payload['enrollment_id'],
                studentId: (int) $payload['student_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                previousClassId: (int) $payload['previous_class_id'],
                previousSectionId: (int) $payload['previous_section_id'],
                classId: (int) $payload['class_id'],
                sectionId: (int) $payload['section_id'],
                specializationId: isset($payload['specialization_id']) ? (int) $payload['specialization_id'] : null,
                updatedBy: isset($payload['updated_by']) ? (int) $payload['updated_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            StudentGradeEntered::class => new StudentGradeEntered(
                gradeId: (int) $payload['grade_id'],
                academicYearId: (int) $payload['academic_year_id'],
                schoolId: (int) $payload['school_id'],
                examEnrollmentId: (int) $payload['exam_enrollment_id'],
                studentId: (int) $payload['student_id'],
                score: isset($payload['score']) ? (string) $payload['score'] : null,
                maxScore: (string) $payload['max_score'],
                isAbsent: (bool) $payload['is_absent'],
                status: (int) $payload['status'],
                enteredBy: isset($payload['entered_by']) ? (int) $payload['entered_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            StudentGradeCorrected::class => new StudentGradeCorrected(
                previousGradeId: (int) $payload['previous_grade_id'],
                newGradeId: (int) $payload['new_grade_id'],
                academicYearId: (int) $payload['academic_year_id'],
                schoolId: (int) $payload['school_id'],
                examEnrollmentId: (int) $payload['exam_enrollment_id'],
                studentId: (int) $payload['student_id'],
                score: isset($payload['score']) ? (string) $payload['score'] : null,
                maxScore: (string) $payload['max_score'],
                isAbsent: (bool) $payload['is_absent'],
                correctedBy: isset($payload['corrected_by']) ? (int) $payload['corrected_by'] : null,
                reason: (string) $payload['reason'],
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            StudentGradeVoided::class => new StudentGradeVoided(
                gradeId: (int) $payload['grade_id'],
                academicYearId: (int) $payload['academic_year_id'],
                schoolId: (int) $payload['school_id'],
                examEnrollmentId: (int) $payload['exam_enrollment_id'],
                studentId: (int) $payload['student_id'],
                voidedBy: isset($payload['voided_by']) ? (int) $payload['voided_by'] : null,
                reason: (string) $payload['reason'],
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            StudentGradeFinalized::class => new StudentGradeFinalized(
                gradeId: (int) $payload['grade_id'],
                academicYearId: (int) $payload['academic_year_id'],
                schoolId: (int) $payload['school_id'],
                examEnrollmentId: (int) $payload['exam_enrollment_id'],
                studentId: (int) $payload['student_id'],
                finalizedBy: isset($payload['finalized_by']) ? (int) $payload['finalized_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamCreated::class => new ExamCreated(
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                termId: (int) $payload['term_id'],
                examTypeId: (int) $payload['exam_type_id'],
                name: (string) $payload['name'],
                startDate: (string) $payload['start_date'],
                endDate: (string) $payload['end_date'],
                status: (int) $payload['status'],
                createdBy: isset($payload['created_by']) ? (int) $payload['created_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamSessionCreated::class => new ExamSessionCreated(
                examSessionId: (int) $payload['exam_session_id'],
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                subjectId: (int) $payload['subject_id'],
                sessionDate: (string) $payload['session_date'],
                startTime: (string) $payload['start_time'],
                endTime: (string) $payload['end_time'],
                roomId: array_key_exists('room_id', $payload) && $payload['room_id'] !== null
                    ? (int) $payload['room_id']
                    : null,
                maxGrade: (int) $payload['max_grade'],
                passGrade: (int) $payload['pass_grade'],
                status: (int) $payload['status'],
                createdBy: isset($payload['created_by']) ? (int) $payload['created_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamSessionUpdated::class => new ExamSessionUpdated(
                examSessionId: (int) $payload['exam_session_id'],
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                status: (int) $payload['status'],
                changedFields: is_array($payload['changed_fields'] ?? null) ? $payload['changed_fields'] : [],
                updatedBy: isset($payload['updated_by']) ? (int) $payload['updated_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamSessionOpened::class => new ExamSessionOpened(
                examSessionId: (int) $payload['exam_session_id'],
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                previousStatus: (int) $payload['previous_status'],
                status: (int) $payload['status'],
                openedBy: isset($payload['opened_by']) ? (int) $payload['opened_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamSessionClosed::class => new ExamSessionClosed(
                examSessionId: (int) $payload['exam_session_id'],
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                previousStatus: (int) $payload['previous_status'],
                status: (int) $payload['status'],
                closedBy: isset($payload['closed_by']) ? (int) $payload['closed_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamUpdated::class => new ExamUpdated(
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                status: (int) $payload['status'],
                changedFields: is_array($payload['changed_fields'] ?? null) ? $payload['changed_fields'] : [],
                updatedBy: isset($payload['updated_by']) ? (int) $payload['updated_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamCancelled::class => new ExamCancelled(
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                cancelledSessionIds: array_map('intval', $payload['cancelled_session_ids'] ?? []),
                withdrawnEnrollmentIds: array_map('intval', $payload['withdrawn_enrollment_ids'] ?? []),
                cancelledBy: isset($payload['cancelled_by']) ? (int) $payload['cancelled_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamSessionCancelled::class => new ExamSessionCancelled(
                examSessionId: (int) $payload['exam_session_id'],
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                previousStatus: (int) $payload['previous_status'],
                cancelledBy: isset($payload['cancelled_by']) ? (int) $payload['cancelled_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
                cause: (string) ($payload['cause'] ?? 'exam_cancel'),
            ),
            ExamEnrollmentCancelled::class => new ExamEnrollmentCancelled(
                examEnrollmentId: (int) $payload['exam_enrollment_id'],
                examSessionId: (int) $payload['exam_session_id'],
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                previousStatus: (int) $payload['previous_status'],
                cancelledBy: isset($payload['cancelled_by']) ? (int) $payload['cancelled_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
                cause: (string) ($payload['cause'] ?? 'exam_cancel'),
            ),
            ExamEnrollmentCreated::class => new ExamEnrollmentCreated(
                examEnrollmentId: (int) $payload['exam_enrollment_id'],
                examSessionId: (int) $payload['exam_session_id'],
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                enrollmentId: (int) $payload['enrollment_id'],
                status: (int) $payload['status'],
                seatNumber: array_key_exists('seat_number', $payload) && $payload['seat_number'] !== null
                    ? (string) $payload['seat_number']
                    : null,
                createdBy: isset($payload['created_by']) ? (int) $payload['created_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            ExamEnrollmentUpdated::class => new ExamEnrollmentUpdated(
                examEnrollmentId: (int) $payload['exam_enrollment_id'],
                examSessionId: (int) $payload['exam_session_id'],
                examId: (int) $payload['exam_id'],
                schoolId: (int) $payload['school_id'],
                enrollmentId: (int) $payload['enrollment_id'],
                previousStatus: (int) $payload['previous_status'],
                status: (int) $payload['status'],
                seatNumber: array_key_exists('seat_number', $payload) && $payload['seat_number'] !== null
                    ? (string) $payload['seat_number']
                    : null,
                changedFields: is_array($payload['changed_fields'] ?? null)
                    ? $payload['changed_fields']
                    : [],
                updatedBy: isset($payload['updated_by']) ? (int) $payload['updated_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            CompletionOutcomeCreated::class => new CompletionOutcomeCreated(
                completionOutcomeId: (int) $payload['completion_outcome_id'],
                schoolId: (int) $payload['school_id'],
                enrollmentId: (int) $payload['enrollment_id'],
                studentId: (int) $payload['student_id'],
                academicYearId: (int) $payload['academic_year_id'],
                createdBy: isset($payload['created_by']) ? (int) $payload['created_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            CompletionEvaluated::class => new CompletionEvaluated(
                completionOutcomeId: (int) $payload['completion_outcome_id'],
                completionOutcomeVersionId: (int) $payload['completion_outcome_version_id'],
                schoolId: (int) $payload['school_id'],
                enrollmentId: (int) $payload['enrollment_id'],
                eligibilityStatus: (int) $payload['eligibility_status'],
                evaluatedBy: isset($payload['evaluated_by']) ? (int) $payload['evaluated_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            GraduationApproved::class => new GraduationApproved(
                approvalId: (int) $payload['approval_id'],
                schoolId: (int) $payload['school_id'],
                enrollmentId: (int) $payload['enrollment_id'],
                completionOutcomeVersionId: (int) $payload['completion_outcome_version_id'],
                decidedBy: (int) $payload['decided_by'],
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            AwardIssued::class => new AwardIssued(
                awardId: (int) $payload['award_id'],
                awardVersionId: (int) $payload['award_version_id'],
                schoolId: (int) $payload['school_id'],
                enrollmentId: (int) $payload['enrollment_id'],
                approvalId: (int) $payload['approval_id'],
                issuedBy: isset($payload['issued_by']) ? (int) $payload['issued_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            AwardRevoked::class => new AwardRevoked(
                revocationId: (int) $payload['revocation_id'],
                awardVersionId: (int) $payload['award_version_id'],
                schoolId: (int) $payload['school_id'],
                reasonRef: (string) $payload['reason_ref'],
                revokedBy: isset($payload['revoked_by']) ? (int) $payload['revoked_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            AttendanceSessionCreated::class => new AttendanceSessionCreated(
                sessionId: (int) $payload['session_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                sectionId: (int) $payload['section_id'],
                subjectId: (int) $payload['subject_id'],
                sessionDate: (string) $payload['session_date'],
                teacherId: (int) $payload['teacher_id'],
                periodId: isset($payload['period_id']) ? (int) $payload['period_id'] : null,
                createdBy: isset($payload['created_by']) ? (int) $payload['created_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            SectionAttendanceMarked::class => new SectionAttendanceMarked(
                sessionId: (int) $payload['session_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                sectionId: (int) $payload['section_id'],
                count: (int) $payload['count'],
                studentIds: array_map('intval', $payload['student_ids'] ?? []),
                recordedBy: isset($payload['recorded_by']) ? (int) $payload['recorded_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            AttendanceCorrected::class => new AttendanceCorrected(
                recordId: (int) $payload['record_id'],
                sessionId: (int) $payload['session_id'],
                studentId: (int) $payload['student_id'],
                enrollmentId: (int) $payload['enrollment_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                previousStatus: (int) $payload['previous_status'],
                newStatus: (int) $payload['new_status'],
                previousNotes: isset($payload['previous_notes']) ? (string) $payload['previous_notes'] : null,
                newNotes: isset($payload['new_notes']) ? (string) $payload['new_notes'] : null,
                reason: (string) $payload['reason'],
                recordedBy: isset($payload['recorded_by']) ? (int) $payload['recorded_by'] : null,
                idempotencyKey: isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            AttendanceSessionClosed::class => new AttendanceSessionClosed(
                sessionId: (int) $payload['session_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                closedBy: isset($payload['closed_by']) ? (int) $payload['closed_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            AttendanceSessionCancelled::class => new AttendanceSessionCancelled(
                sessionId: (int) $payload['session_id'],
                schoolId: (int) $payload['school_id'],
                academicYearId: (int) $payload['academic_year_id'],
                previousStatus: (int) $payload['previous_status'],
                newStatus: (int) $payload['new_status'],
                reason: (string) $payload['reason'],
                cancelledBy: isset($payload['cancelled_by']) ? (int) $payload['cancelled_by'] : null,
                occurredAt: new \DateTimeImmutable((string) $payload['occurred_at']),
            ),
            default => null,
        };
    }
}
