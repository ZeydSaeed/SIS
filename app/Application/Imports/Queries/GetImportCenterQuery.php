<?php

namespace App\Application\Imports\Queries;

use App\Application\Contracts\Query;

/** «مركز الاستيراد»: the kinds the user may import (columns, template) and the school's latest batches. */
final readonly class GetImportCenterQuery implements Query
{
    /** @param  list<string>  $allowedKinds */
    public function __construct(
        public int $schoolId,
        public array $allowedKinds,
        public ?int $batchId = null,
        public ?int $rowStatus = null,
        public int $page = 1,
    ) {}
}
