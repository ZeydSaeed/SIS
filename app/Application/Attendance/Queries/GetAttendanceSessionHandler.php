<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceReadRepositoryInterface;
use App\Application\Attendance\DTOs\AttendanceSessionDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;

final class GetAttendanceSessionHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceReadRepositoryInterface $attendance,
    ) {}

    public function handle(Query $query): AttendanceSessionDTO
    {
        assert($query instanceof GetAttendanceSessionQuery);

        $dto = $this->attendance->getSession($query->schoolId, $query->sessionId, $query->includeRecords);
        if ($dto === null) {
            throw SessionNotFoundException::forId($query->sessionId);
        }

        return $dto;
    }
}
