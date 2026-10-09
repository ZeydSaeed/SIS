<?php

namespace App\Application\Imports\Queries;

use App\Application\Contracts\Query;

/** «تنزيل القالب» (batchId null) or «تنزيل تقرير الأخطاء» (the batch's error / duplicate / failed rows). */
final readonly class BuildImportSheetQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public string $kind,
        public ?int $batchId = null,
    ) {}
}
