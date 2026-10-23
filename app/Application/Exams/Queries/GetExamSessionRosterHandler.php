<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\ExamSessionRosterReadRepositoryInterface;

final class GetExamSessionRosterHandler
{
    public function __construct(
        private readonly ExamSessionRosterReadRepositoryInterface $roster,
    ) {}

    /**
     * Null when the session is not in the school or does not belong to the exam.
     *
     * @return array{session: array<string, mixed>, seats: list<array<string, mixed>>}|null
     */
    public function handle(GetExamSessionRosterQuery $query): ?array
    {
        $session = $this->roster->session($query->schoolId, $query->examSessionId);
        if ($session === null || $session['exam_id'] !== $query->examId) {
            return null;
        }

        return [
            'session' => $session,
            'seats' => $this->roster->seats($query->schoolId, $query->examSessionId),
        ];
    }
}
