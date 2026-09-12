<?php

namespace App\Security\Audit;

enum SecurityEventType: string
{
    case LoginSuccess = 'SEC_LOGIN_SUCCESS';
    case LoginFailed = 'SEC_LOGIN_FAILED';
    case Logout = 'SEC_LOGOUT';
    case Unauthorized = 'SEC_UNAUTHORIZED';
    case Forbidden = 'SEC_FORBIDDEN';
    case IdorBlocked = 'SEC_IDOR_BLOCKED';
    case RateLimited = 'SEC_RATE_LIMITED';
    case PolicyBlock = 'SEC_POLICY_BLOCK';
    case PrivilegeChanged = 'SEC_PRIVILEGE_CHANGED';
    case BulkExport = 'SEC_BULK_EXPORT';
    case BulkDelete = 'SEC_BULK_DELETE';
    case FileBlocked = 'SEC_FILE_BLOCKED';
    case SuspiciousRequest = 'SEC_SUSPICIOUS_REQUEST';
    case StudentDataAccess = 'SEC_STUDENT_DATA_ACCESS';
    case StudentDataModified = 'SEC_STUDENT_DATA_MODIFIED';
    case EnrollmentDataAccess = 'SEC_ENROLLMENT_DATA_ACCESS';
    case EnrollmentDataModified = 'SEC_ENROLLMENT_DATA_MODIFIED';
    case GradeDataAccess = 'SEC_GRADE_DATA_ACCESS';
    case GradeDataModified = 'SEC_GRADE_DATA_MODIFIED';
    case AttendanceDataAccess = 'SEC_ATTENDANCE_DATA_ACCESS';
    case AttendanceDataModified = 'SEC_ATTENDANCE_DATA_MODIFIED';
    case TimetableDataModified = 'SEC_TIMETABLE_DATA_MODIFIED';
    case TimetableDataAccess = 'SEC_TIMETABLE_DATA_ACCESS';
    case ResultsDataAccess = 'SEC_RESULTS_DATA_ACCESS';
    case ResultsDataModified = 'SEC_RESULTS_DATA_MODIFIED';
    case VocationalDataModified = 'SEC_VOCATIONAL_DATA_MODIFIED';
    case VocationalDataAccess = 'SEC_VOCATIONAL_DATA_ACCESS';
}
