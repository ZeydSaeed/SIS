<?php

namespace App\Infrastructure\Persistence\Student;

use App\Database\SchemaHelper;
use App\Domain\Student\Data\StudentGuardianLinkSnapshot;
use App\Domain\Student\Repositories\StudentGuardianRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentStudentGuardianRepository implements StudentGuardianRepositoryInterface
{
    public function listForStudent(int $schoolId, int $studentId): ?array
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $studentExists = DB::table(SchemaHelper::qualified('students', 'students'))
            ->where('id', $studentId)
            ->where('school_id', $schoolId)
            ->exists();

        if (! $studentExists) {
            return null;
        }

        return DB::table(SchemaHelper::qualified('guardians', 'student_guardians').' as sg')
            ->join(SchemaHelper::qualified('guardians', 'guardians').' as g', 'g.id', '=', 'sg.guardian_id')
            ->where('sg.student_id', $studentId)
            ->orderByDesc('sg.is_primary')
            ->orderBy('sg.id')
            ->get([
                'sg.id',
                'sg.student_id',
                'sg.guardian_id',
                'sg.relationship_type',
                'sg.is_primary',
                'sg.is_emergency_contact',
                'g.full_name',
                'g.phone',
                'g.email',
                'sg.created_at',
            ])
            ->map(static fn (object $row): StudentGuardianLinkSnapshot => new StudentGuardianLinkSnapshot(
                linkId: (int) $row->id,
                studentId: (int) $row->student_id,
                guardianId: (int) $row->guardian_id,
                relationshipType: (int) $row->relationship_type,
                isPrimary: (bool) $row->is_primary,
                isEmergencyContact: (bool) $row->is_emergency_contact,
                guardianFullName: (string) $row->full_name,
                guardianPhone: $row->phone !== null ? (string) $row->phone : null,
                guardianEmail: $row->email !== null ? (string) $row->email : null,
                createdAt: (string) $row->created_at,
            ))
            ->all();
    }
}
