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
    case TeacherDataAccess = 'SEC_TEACHER_DATA_ACCESS';
    case TeacherDataModified = 'SEC_TEACHER_DATA_MODIFIED';
    case PromotionDataAccess = 'SEC_PROMOTION_DATA_ACCESS';
    case PromotionDataModified = 'SEC_PROMOTION_DATA_MODIFIED';
    case TransfersDataAccess = 'SEC_TRANSFERS_DATA_ACCESS';
    case TransfersDataModified = 'SEC_TRANSFERS_DATA_MODIFIED';
    case DocumentsDataAccess = 'SEC_DOCUMENTS_DATA_ACCESS';
    case DocumentsDataModified = 'SEC_DOCUMENTS_DATA_MODIFIED';
    case FinanceDataAccess = 'SEC_FINANCE_DATA_ACCESS';
    case FinanceDataModified = 'SEC_FINANCE_DATA_MODIFIED';
    case CommunicationDataAccess = 'SEC_COMMUNICATION_DATA_ACCESS';
    case CommunicationDataModified = 'SEC_COMMUNICATION_DATA_MODIFIED';
    case WorkflowDataAccess = 'SEC_WORKFLOW_DATA_ACCESS';
    case WorkflowDataModified = 'SEC_WORKFLOW_DATA_MODIFIED';
    case HrDataAccess = 'SEC_HR_DATA_ACCESS';
    case HrDataModified = 'SEC_HR_DATA_MODIFIED';
    case CurriculumDataAccess = 'SEC_CURRICULUM_DATA_ACCESS';
    case CurriculumDataModified = 'SEC_CURRICULUM_DATA_MODIFIED';
    case AuditTrailDataAccess = 'SEC_AUDIT_TRAIL_DATA_ACCESS';
    case AuditTrailDataModified = 'SEC_AUDIT_TRAIL_DATA_MODIFIED';
}
