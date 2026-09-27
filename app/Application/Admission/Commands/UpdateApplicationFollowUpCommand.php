<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateApplicationFollowUpCommand implements Command
{
    /**
     * @param  list<array{
     *     application_id:int,
     *     full_name:?string,
     *     rejection_reason:?string,
     *     withdrawal_reason:?string,
     *     update_rejection_reason:bool,
     *     update_withdrawal_reason:bool
     * }>  $updates
     */
    public function __construct(
        public int $schoolId,
        public array $updates,
        public ?string $idempotencyKey = null,
    ) {}
}
