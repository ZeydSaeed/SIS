<?php

namespace App\Console\Commands;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Student\StudentPlacementIdResolver;
use App\Security\Context\SchoolContextScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow A1 backfill — fill students.department_id / grade_level_id (and a missing
 * branch_id) from the admission placement text. Runs per school inside the school
 * context (RLS-safe), only fills NULL columns, and is safe to re-run.
 *
 * php artisan sis:backfill-student-placement-ids
 * php artisan sis:backfill-student-placement-ids --school=3 --dry-run
 */
class BackfillStudentPlacementIdsCommand extends Command
{
    protected $signature = 'sis:backfill-student-placement-ids
                            {--school= : Only this school id}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill structured placement ids on students from department / class names';

    public function handle(SchoolContextScope $scope, StudentPlacementIdResolver $resolver): int
    {
        $students = SchemaHelper::qualified('students', 'students');
        // The names were dropped once every student carried ids — nothing left to backfill.
        if (! Schema::hasColumn($students, 'department_name') || ! Schema::hasColumn($students, 'admitted_class_name')) {
            $this->components->info('Placement names are no longer stored on students — nothing to backfill.');

            return self::SUCCESS;
        }

        $schoolIds = DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->when($this->option('school') !== null, fn ($query) => $query->where('id', (int) $this->option('school')))
            ->orderBy('id')
            ->pluck('id');

        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($schoolIds as $schoolId) {
            $updated = $scope->run((int) $schoolId, function () use ($students, $resolver, $schoolId, $dryRun): int {
                $count = 0;
                DB::table($students)
                    ->where('school_id', $schoolId)
                    ->where(fn ($query) => $query->whereNull('department_id')->orWhereNull('grade_level_id'))
                    ->orderBy('id')
                    ->select(['id', 'branch_id', 'department_id', 'grade_level_id', 'department_name', 'admitted_class_name'])
                    ->chunkById(500, function ($rows) use ($students, $resolver, $schoolId, $dryRun, &$count): void {
                        foreach ($rows as $row) {
                            $changes = $this->missingOnly($row, $resolver->resolve(
                                (int) $schoolId,
                                $row->branch_id !== null ? (int) $row->branch_id : null,
                                $row->department_name,
                                $row->admitted_class_name,
                            ));
                            if ($changes === []) {
                                continue;
                            }

                            $count++;
                            if (! $dryRun) {
                                DB::table($students)->where('id', $row->id)->update($changes + ['updated_at' => now()]);
                            }
                        }
                    });

                return $count;
            });

            $this->line("school {$schoolId}: {$updated} student(s) ".($dryRun ? 'would be updated' : 'updated'));
            $total += $updated;
        }

        $this->components->info(($dryRun ? 'Dry run — ' : '')."{$total} student(s) total.");

        return self::SUCCESS;
    }

    /**
     * @param  array{branch_id: int|null, department_id: int|null, grade_level_id: int|null}  $resolved
     * @return array<string, int>
     */
    private function missingOnly(object $row, array $resolved): array
    {
        $changes = [];
        foreach ($resolved as $column => $value) {
            if ($value !== null && $row->{$column} === null) {
                $changes[$column] = $value;
            }
        }

        return $changes;
    }
}
