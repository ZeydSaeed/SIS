<?php

namespace Database\Seeders\Support;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

/**
 * Soft/data wipe for protected `sis` DB (migrate:fresh is blocked).
 * Enrollments/students cannot hard-delete — cancel + deactivate + free unique keys.
 */
final class WorkflowDataWiper
{
    public function wipeDemoSchool(): void
    {
        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');

        if ($schoolId < 1) {
            return;
        }

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $periodIds = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('school_id', $schoolId)
            ->pluck('id')
            ->all();

        $appIds = $periodIds === []
            ? []
            : DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->whereIn('application_period_id', $periodIds)
                ->pluck('id')
                ->all();

        $studentIds = DB::table(SchemaHelper::qualified('students', 'students'))
            ->where('school_id', $schoolId)
            ->pluck('id')
            ->all();

        // 1) Cancel enrollments (hard DELETE forbidden).
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->update([
                'status' => 5,
                'effective_to' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        // 2) Detach applications from students, remove docs + applications + periods.
        if ($appIds !== []) {
            DB::table(SchemaHelper::qualified('admission', 'application_documents'))
                ->whereIn('application_id', $appIds)
                ->delete();

            DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->whereIn('id', $appIds)
                ->update(['student_id' => null, 'updated_at' => now()]);

            DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->whereIn('id', $appIds)
                ->delete();
        }

        if ($periodIds !== []) {
            DB::table(SchemaHelper::qualified('admission', 'application_periods'))
                ->whereIn('id', $periodIds)
                ->delete();
        }

        // 3) Deactivate students and free unique national_id / student_code for reseed.
        if ($studentIds !== []) {
            foreach ($studentIds as $studentId) {
                $token = substr(md5((string) $studentId.microtime(true)), 0, 12);
                DB::table(SchemaHelper::qualified('students', 'students'))
                    ->where('id', $studentId)
                    ->update([
                        'status' => 0,
                        'national_id' => 'A'.$token, // max 20
                        'student_code' => 'Z'.$token,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}
