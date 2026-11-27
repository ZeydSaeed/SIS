<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

/**
 * «إنشاء منهج دراسي» in one step: the curriculum with its subjects (CreateCurriculumCommand), then the teachers of
 * each subject — the whole subject or one part of it (نظري، عملي …), a main teacher and assistants.
 */
final readonly class CreateCurriculumWithTeachersCommand implements Command
{
    /**
     * @param  list<array{subject_id: int, subject_type: ?int, teacher_id: int, role: int, section_id: ?int}>  $teachers
     */
    public function __construct(
        public CreateCurriculumCommand $curriculum,
        public array $teachers,
    ) {}
}
