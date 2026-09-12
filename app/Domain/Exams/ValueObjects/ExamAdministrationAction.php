<?php

namespace App\Domain\Exams\ValueObjects;

enum ExamAdministrationAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Cancel = 'cancel';
    case CreateSession = 'create_session';
    case UpdateSession = 'update_session';
    case OpenSession = 'open_session';
    case CloseSession = 'close_session';
    case CreateEnrollment = 'create_enrollment';
    case UpdateEnrollment = 'update_enrollment';
    case CancelEnrollment = 'cancel_enrollment';
    case PresentEnrollment = 'present_enrollment';
}
