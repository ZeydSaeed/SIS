<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateDirectorateCommand implements Command
{
    /**
     * @param  array{name?: string, region?: ?string}  $fields
     * @param  list<int>|null  $schoolIds  Desired set of the user's schools in the directorate (null = unchanged).
     * @param  list<int>  $allowedSchoolIds  Schools the user is linked to (resolved server-side).
     */
    public function __construct(
        public int $directorateId,
        public array $fields,
        public ?array $schoolIds,
        public array $allowedSchoolIds,
        public ?string $idempotencyKey,
    ) {}
}
