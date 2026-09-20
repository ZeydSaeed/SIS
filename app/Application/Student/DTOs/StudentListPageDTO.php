<?php

namespace App\Application\Student\DTOs;

use App\Domain\Student\ValueObjects\StudentStatus;

final readonly class StudentListPageDTO
{
    /**
     * @param  list<StudentListItemDTO>  $items
     * @param  array{page: int, per_page: int, total: int, last_page: int}  $pagination
     * @param  array{overall_percent: int, stages: list<array{status: int|null, percent: int}>}  $statusProgress
     */
    public function __construct(
        public array $items,
        public array $pagination,
        public array $statusProgress = ['overall_percent' => 0, 'stages' => []],
    ) {}

    /**
     * @param  array{items: list<StudentListItemDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}  $page
     * @param  array<int, int>  $countsByStatus
     */
    public static function fromPage(array $page, array $countsByStatus): self
    {
        return new self(
            items: $page['items'],
            pagination: $page['pagination'],
            statusProgress: self::progressFromCounts($countsByStatus),
        );
    }

    /**
     * @param  array<int, int>  $countsByStatus
     * @return array{overall_percent: int, stages: list<array{status: int|null, percent: int}>}
     */
    public static function progressFromCounts(array $countsByStatus): array
    {
        $total = 0;
        foreach ($countsByStatus as $count) {
            $total += max(0, (int) $count);
        }

        $percentOf = static function (int $count) use ($total): int {
            return $total === 0 ? 0 : (int) round(($count / $total) * 100);
        };

        $stages = [
            ['status' => null, 'percent' => $total === 0 ? 0 : 100],
        ];
        foreach ([
            StudentStatus::Active->value,
            StudentStatus::Inactive->value,
            StudentStatus::Suspended->value,
            StudentStatus::Graduated->value,
            StudentStatus::Withdrawn->value,
        ] as $status) {
            $stages[] = [
                'status' => $status,
                'percent' => $percentOf((int) ($countsByStatus[$status] ?? 0)),
            ];
        }

        return [
            'overall_percent' => $percentOf((int) ($countsByStatus[StudentStatus::Active->value] ?? 0)),
            'stages' => $stages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn (StudentListItemDTO $item): array => $item->toArray(), $this->items),
            'meta' => $this->pagination,
            'status_progress' => $this->statusProgress,
        ];
    }
}
