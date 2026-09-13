<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;

final readonly class VoidStudentDocumentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $documentId,
        public ?string $idempotencyKey,
    ) {}
}
