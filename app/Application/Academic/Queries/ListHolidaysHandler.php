<?php

namespace App\Application\Academic\Queries;

use App\Application\Academic\DTOs\HolidayDTO;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;

final class ListHolidaysHandler
{
    public function __construct(
        private readonly HolidayRepositoryInterface $holidays,
    ) {}

    /**
     * @return list<HolidayDTO>
     */
    public function handle(ListHolidaysQuery $query): array
    {
        return array_map(
            static fn ($row): HolidayDTO => new HolidayDTO(
                id: $row->id,
                academicYearId: $row->academicYearId,
                schoolId: $row->schoolId,
                name: $row->name,
                startDate: $row->startDate,
                endDate: $row->endDate,
                holidayType: $row->holidayType,
                createdAt: $row->createdAt,
            ),
            $this->holidays->listVisible($query->schoolId, $query->academicYearId),
        );
    }
}
