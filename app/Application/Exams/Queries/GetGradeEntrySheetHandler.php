<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\GradeEntryScopePort;
use App\Application\Exams\Contracts\GradeEntrySheetReadRepositoryInterface;

/**
 * Scope is applied here, on the server: without school-wide entry the session list holds only sessions with a
 * covered seat and the roster only the covered seats. A chosen session with no covered seat is reported as
 * forbidden (never as an empty roster) so the caller can deny and audit it.
 */
final class GetGradeEntrySheetHandler
{
    public function __construct(
        private readonly GradeEntrySheetReadRepositoryInterface $sheet,
        private readonly GradeEntryScopePort $scope,
    ) {}

    /**
     * @return array{sessions: list<array<string, mixed>>, session: array<string, mixed>|null, rows: list<array<string, mixed>>,
     *     session_missing: bool, session_forbidden: bool}
     */
    public function handle(GetGradeEntrySheetQuery $query): array
    {
        $sessionIds = $query->schoolWide ? null : $this->scope->coveredSessionIds($query->actorUserId, $query->schoolId, $query->academicYearId);
        $result = [
            'sessions' => $this->sheet->sessions($query->schoolId, $query->academicYearId, $sessionIds),
            'session' => null,
            'rows' => [],
            'session_missing' => false,
            'session_forbidden' => false,
        ];

        if ($query->examSessionId === null) {
            return $result;
        }

        $session = $this->sheet->session($query->schoolId, $query->examSessionId);
        if ($session === null) {
            return ['session_missing' => true] + $result;
        }

        $seatIds = $query->schoolWide ? null : $this->scope->coveredSeatIds($query->actorUserId, $query->schoolId, $query->examSessionId);
        if ($seatIds === []) {
            return ['session_forbidden' => true] + $result;
        }

        return ['session' => $session, 'rows' => $this->sheet->seats($query->schoolId, $query->examSessionId, $seatIds)] + $result;
    }
}
