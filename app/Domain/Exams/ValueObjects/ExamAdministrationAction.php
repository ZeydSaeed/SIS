<?php

namespace App\Domain\Exams\ValueObjects;

enum ExamAdministrationAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Cancel = 'cancel';
}
