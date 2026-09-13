<?php

namespace App\Application\Communication\Queries;

final readonly class GetNotificationTemplateQuery
{
    public function __construct(
        public int $schoolId,
        public int $templateId,
    ) {}
}
