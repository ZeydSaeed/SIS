<?php

namespace App\Domain\Leave\ValueObjects;

/** Who a leave request belongs to: a teacher (teachers.teachers) or an administrative employee (hr.employees). */
enum RequesterKind: int
{
    case Teacher = 1;
    case Employee = 2;
}
