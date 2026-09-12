<?php

namespace App\Application\Communication\Queries;

final readonly class ListNotificationTemplatesQuery
{
    public function __construct(
        public int $schoolId,
        public ?bool $activeOnly = null,
    ) {}
}
