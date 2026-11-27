<?php

namespace App\Application\Curriculum\Commands;

/** Thrown inside the transaction of CreateCurriculumWithTeachersHandler so a refusal undoes the whole curriculum. */
final class CurriculumCreationRefused extends \RuntimeException
{
    /** @param  list<string>  $errors */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct($errors[0] ?? 'curriculum.curriculum_create_failed');
    }
}
