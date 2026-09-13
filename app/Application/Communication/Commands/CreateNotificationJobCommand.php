<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;

final readonly class CreateNotificationJobCommand implements Command
{
    /**
     * @param  array<string, mixed>  $targetFilter
     */
    public function __construct(
        public int $schoolId,
        public int $templateId,
        public array $targetFilter,
        public int $totalCount,
        public ?int $createdBy,
        public ?string $idempotencyKey,
    ) {}
}
