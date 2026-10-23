<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Application\Exams\Contracts\GradeListStudentsReadRepositoryInterface;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentGradeListStudentsReadRepository implements GradeListStudentsReadRepositoryInterface
{
    public function students(int $schoolId, array $enrollmentIds): array
    {
        $enrollmentIds = array_values(array_unique($enrollmentIds));
        if ($enrollmentIds === []) {
            return [];
        }

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as en')
            ->join(SchemaHelper::qualified('students', 'students').' as st', 'st.id', '=', 'en.student_id')
            ->where('en.school_id', $schoolId)
            ->whereIn('en.id', $enrollmentIds)
            ->get(['en.id', 'st.full_name', 'st.student_code'])
            ->mapWithKeys(static fn (object $r): array => [
                (int) $r->id => ['student_name' => (string) $r->full_name, 'student_code' => (string) $r->student_code],
            ])
            ->all();
    }
}
