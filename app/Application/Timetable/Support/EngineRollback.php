<?php

namespace App\Application\Timetable\Support;

use App\Application\Timetable\Results\TimetableEngineResult;

/** Carries a failed result out of the transaction so it rolls back ({@see EngineTransaction}). */
final class EngineRollback extends \RuntimeException
{
    public function __construct(public readonly TimetableEngineResult $result)
    {
        parent::__construct(implode(',', $result->errors));
    }
}
