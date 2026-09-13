<?php

namespace App\Domain\Communication\Data;

final readonly class NotificationJobSnapshot
{
    /**
     * @param  array<string, mixed>  $targetFilter
     */
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $jobId,
        public int $templateId,
        public array $targetFilter,
        public int $totalCount,
        public int $sentCount,
        public int $status,
        public ?int $createdBy,
        public string $createdAt,
        public ?string $completedAt,
    ) {}
}
