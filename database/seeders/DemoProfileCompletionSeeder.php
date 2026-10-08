<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Last step of the demo rebuild: fills the optional profile fields the admission → student pipeline does not
 * carry (they are entered on the student page in real use) and the audit actors, so a column that is still
 * empty afterwards points at a real gap — see `php artisan sis:audit-data-quality --fill`.
 *
 * Only NULL / empty values are written; running it again changes nothing. Legitimately empty columns stay
 * empty: transfer details of students who did not transfer, `middle_name` (legacy — father_name replaces
 * it), photos (no files in the demo).
 */
final class DemoProfileCompletionSeeder extends Seeder
{
    public function run(): void
    {
        $actor = (int) (DB::table('users')->orderBy('id')->value('id') ?? 0);
        $students = SchemaHelper::qualified('students', 'students');
        $blank = static fn (string $col): string => "({$col} IS NULL OR btrim({$col}) = '')";

        DB::statement("UPDATE {$students} SET
            nationality = COALESCE(NULLIF(btrim(nationality), ''), 'عراقي'),
            locality = CASE WHEN {$blank('locality')} THEN COALESCE(neighborhood, governorate) ELSE locality END,
            house_number = CASE WHEN {$blank('house_number')} THEN (10 + id % 90)::text ELSE house_number END,
            registration_place = CASE WHEN {$blank('registration_place')} THEN COALESCE(birth_place, governorate) ELSE registration_place END,
            mawalid_date = COALESCE(mawalid_date, birth_date),
            guardian_triple_name = CASE WHEN {$blank('guardian_triple_name')}
                THEN btrim(concat_ws(' ', father_name, grandfather_name, great_grandfather_name)) ELSE guardian_triple_name END,
            school_start_date = COALESCE(school_start_date, DATE '2026-09-01'),
            email = CASE WHEN {$blank('email')} THEN lower(student_code) || '@students.sis.local' ELSE email END,
            mobile = CASE WHEN {$blank('mobile')} THEN '075' || lpad((20000000 + id * 3719)::text, 8, '0') ELSE mobile END,
            transfer_document_number = CASE WHEN previous_school_name IS NOT NULL THEN COALESCE(transfer_document_number, 1000 + id) ELSE transfer_document_number END,
            transfer_document_date = CASE WHEN previous_school_name IS NOT NULL THEN COALESCE(transfer_document_date, DATE '2026-08-15') ELSE transfer_document_date END,
            updated_at = now()
            WHERE true");

        // Personal lesson limits by نوع التعيين: [weekly min, weekly max, daily max].
        $quota = [1 => [18, 24, 6], 2 => [16, 24, 6], 3 => [10, 18, 5], 4 => [6, 12, 4], 5 => [12, 20, 6]];
        foreach ($quota as $type => [$min, $max, $daily]) {
            DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
                ->where('employment_type', $type)->whereNull('left_at')
                ->whereNull('weekly_lessons_min')->whereNull('weekly_lessons_max')->whereNull('daily_lessons_max')
                ->update(['weekly_lessons_min' => $min, 'weekly_lessons_max' => $max, 'daily_lessons_max' => $daily]);
        }

        $directorate = DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', Support\FoundationReference::SCHOOL_CODE)->value('directorate_id');
        if ($directorate !== null) {
            DB::table(SchemaHelper::qualified('admission', 'application_periods'))
                ->whereNull('directorate_id')->update(['directorate_id' => $directorate]);
        }

        if ($actor > 0) {
            DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->whereNull('reviewed_by')->where('status', '>=', 3)->update(['reviewed_by' => $actor]);
            DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
                ->whereNull('enrolled_by')->update(['enrolled_by' => $actor]);
            DB::table(SchemaHelper::qualified('students', 'student_documents'))
                ->whereNull('uploaded_by')->update(['uploaded_by' => $actor]);
        }
    }
}
