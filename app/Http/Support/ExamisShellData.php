<?php

namespace App\Http\Support;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use Illuminate\Http\Request;

/**
 * The exams / grades / results pages' shared «الأقسام» ribbon group: which sections the viewer may open — the same
 * checks the pages' controllers make (every page re-checks on the server).
 */
final class ExamisShellData
{
    /**
     * @return array{sections: array<string, bool>}|null
     */
    public function build(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        $exams = $user->can('viewAny', ExamRecord::class);
        $results = $user->can('viewOfficial', TermResultRecord::class);

        return ['sections' => [
            'exams' => $exams,
            'types' => $exams,
            'gradeSheet' => $user->can('create', StudentGradeRecord::class),
            'grades' => $user->can('viewAny', StudentGradeRecord::class),
            'reviews' => $user->can('viewAny', StudentGradeRecord::class),
            'termSheet' => $results,
            'yearSheet' => $results,
            'results' => $results,
            'transcript' => $results,
        ]];
    }
}
