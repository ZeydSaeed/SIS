<?php

namespace App\Application\Enrollment\DTOs;

use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;

final readonly class EnrollmentListPageDTO
{
    /**
     * @param  list<EnrollmentDTO>  $items
     * @param  array{page: int, per_page: int, total: int, last_page: int}  $pagination
     * @param  array{overall_percent: int, stages: list<array{status: int|null, percent: int, count: int}>}  $statusProgress
     */
    public function __construct(
        public array $items,
        public array $pagination,
        public array $statusProgress = ['overall_percent' => 0, 'stages' => []],
    ) {}

    /**
     * @param  array{items: list<EnrollmentDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}  $page
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
     * @return array{overall_percent: int, stages: list<array{status: int|null, percent: int, count: int}>}
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
            ['status' => null, 'percent' => $total === 0 ? 0 : 100, 'count' => $total],
        ];
        foreach ([
            EnrollmentStatus::ACTIVE,
            EnrollmentStatus::INACTIVE,
            EnrollmentStatus::CANCELLED,
            EnrollmentStatus::TRANSFERRED,
        ] as $status) {
            $count = (int) ($countsByStatus[$status] ?? 0);
            $stages[] = [
                'status' => $status,
                'percent' => $percentOf($count),
                'count' => $count,
            ];
        }

        return [
            'overall_percent' => $percentOf((int) ($countsByStatus[EnrollmentStatus::ACTIVE] ?? 0)),
            'stages' => $stages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn (EnrollmentDTO $item): array => $item->toArray(), $this->items),
            'meta' => $this->pagination,
            'status_progress' => $this->statusProgress,
        ];
    }
}
