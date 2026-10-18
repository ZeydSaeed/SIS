<?php

namespace App\Application\Leave\Support;

/**
 * What the reading user may see, computed by the HTTP layer from the permission catalogue (server-side only):
 * all of the school's requests or only their own, sensitive details, balances, students' excuses.
 */
final readonly class LeaveViewer
{
    public function __construct(
        public int $userId,
        public bool $seesAll,
        public bool $seesSensitive,
        public bool $seesBalances,
        public bool $seesStudentExcuses = false,
    ) {}

    /** Only the user's own requests (as the person on leave or as the one who entered them). */
    public function ownScope(): ?int
    {
        return $this->seesAll ? null : $this->userId;
    }
}
