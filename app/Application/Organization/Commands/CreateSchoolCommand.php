<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class CreateSchoolCommand implements Command
{
    /**
     * @param  int  $sourceSchoolId  Current school context — the creator's roles there are copied to the new school.
     */
    public function __construct(
        public int $createdByUserId,
        public int $sourceSchoolId,
        public int $directorateId,
        public string $name,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public ?string $idempotencyKey,
        /** نشط / غير نشط / مؤرشف (SchoolStatus). */
        public int $status = 1,
    ) {}
}
