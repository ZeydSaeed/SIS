<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class CreateDirectorateCommand implements Command
{
    /**
     * @param  list<int>  $schoolIds  The user's schools to place in the new directorate.
     * @param  list<int>  $allowedSchoolIds  Schools the user is linked to (resolved server-side).
     */
    public function __construct(
        public string $name,
        public ?string $region,
        public array $schoolIds,
        public array $allowedSchoolIds,
        public ?string $idempotencyKey,
    ) {}
}
