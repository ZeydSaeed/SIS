<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expand students.student_documents.document_type to include 20 = personal photo (صورة شخصية).
 * Metadata only — binary in object storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE students.student_documents DROP CONSTRAINT IF EXISTS students_student_documents_type_check');
        DB::statement(
            'ALTER TABLE students.student_documents
             ADD CONSTRAINT students_student_documents_type_check
             CHECK (document_type IN (1, 2, 3, 4, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE students.student_documents DROP CONSTRAINT IF EXISTS students_student_documents_type_check');
        DB::statement(
            'ALTER TABLE students.student_documents
             ADD CONSTRAINT students_student_documents_type_check
             CHECK (document_type IN (1, 2, 3, 4, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19))'
        );
    }
};
