<?php

namespace App\Security\Authorization;

final class Permission
{
    public const STUDENTS_VIEW = 'students.view';

    public const STUDENTS_CREATE = 'students.create';

    public const STUDENTS_UPDATE = 'students.update';

    public const STUDENTS_VIEW_PII = 'students.view_pii';

    public const ENROLLMENT_VIEW = 'enrollment.view';

    public const ENROLLMENT_CREATE = 'enrollment.create';

    public const ENROLLMENT_UPDATE = 'enrollment.update';

    public const ENROLLMENT_CANCEL = 'enrollment.cancel';

    public const GRADES_VIEW = 'grades.view';

    public const GRADES_CREATE = 'grades.create';

    public const GRADES_CORRECT = 'grades.correct';

    public const GRADES_VOID = 'grades.void';

    public const GRADES_FINALIZE = 'grades.finalize';

    public const EXAM_CREATE = 'exam.create';

    public const EXAM_UPDATE = 'exam.update';

    public const EXAM_CANCEL = 'exam.cancel';

    public const EXAM_SESSION_CREATE = 'exam.session.create';

    public const EXAM_SESSION_UPDATE = 'exam.session.update';

    public const EXAM_SESSION_OPEN = 'exam.session.open';

    public const EXAM_SESSION_CLOSE = 'exam.session.close';

    public const EXAM_ENROLLMENT_CREATE = 'exam.enrollment.create';

    public const EXAM_ENROLLMENT_UPDATE = 'exam.enrollment.update';

    public const EXAM_ENROLLMENT_CANCEL = 'exam.enrollment.cancel';

    public const EXAM_ENROLLMENT_PRESENT = 'exam.enrollment.present';

    public const ATTENDANCE_VIEW = 'attendance.view';

    public const ATTENDANCE_SESSION_CREATE = 'attendance.session.create';

    public const ATTENDANCE_MARK = 'attendance.mark';

    public const ATTENDANCE_CORRECT = 'attendance.correct';

    public const ATTENDANCE_SESSION_CLOSE = 'attendance.session.close';

    public const ATTENDANCE_SESSION_CANCEL = 'attendance.session.cancel';

    public const TIMETABLE_SCHEDULE_CREATE = 'timetable.schedule.create';

    public const TIMETABLE_VIEW = 'timetable.view';

    public const TIMETABLE_SCHEDULE_UPDATE = 'timetable.schedule.update';

    public const TIMETABLE_SCHEDULE_CANCEL = 'timetable.schedule.cancel';

    public const TIMETABLE_EXCEPTION_CREATE = 'timetable.exception.create';

    public const TIMETABLE_EXCEPTION_UPDATE = 'timetable.exception.update';

    public const RESULTS_VIEW = 'results.view';

    public const RESULTS_CALCULATE = 'results.calculate';

    public const RESULTS_FINALIZE = 'results.finalize';

    public const RESULTS_RANKING_BUILD = 'results.ranking.build';

    public const RESULTS_TRANSCRIPT_ISSUE = 'results.transcript.issue';

    public const RESULTS_REBUILD = 'results.rebuild';

    public const PORTAL_RESULTS_VIEW = 'portal.results.view';

    public const PORTAL_SCOPES_MANAGE = 'portal.scopes.manage';

    public const TEACHERS_VIEW = 'teachers.view';

    public const TEACHERS_MANAGE = 'teachers.manage';

    public const PROMOTION_VIEW = 'promotion.view';

    public const PROMOTION_MANAGE = 'promotion.manage';

    public const TRANSFERS_VIEW = 'transfers.view';

    public const TRANSFERS_MANAGE = 'transfers.manage';

    public const DOCUMENTS_VIEW = 'documents.view';

    public const DOCUMENTS_MANAGE = 'documents.manage';

    public const FINANCE_VIEW = 'finance.view';

    public const FINANCE_MANAGE = 'finance.manage';

    public const COMMUNICATION_VIEW = 'communication.view';

    public const COMMUNICATION_MANAGE = 'communication.manage';

    public const WORKFLOW_VIEW = 'workflow.view';

    public const WORKFLOW_MANAGE = 'workflow.manage';

    public const WORKFLOW_DECIDE = 'workflow.decide';

    public const HR_VIEW = 'hr.view';

    public const HR_MANAGE = 'hr.manage';

    public const HR_PAYROLL_VIEW = 'hr.payroll.view';

    public const HR_PAYROLL_MANAGE = 'hr.payroll.manage';

    public const VOCATIONAL_MANAGE = 'vocational.manage';

    public const VOCATIONAL_VIEW = 'vocational.view';

    public const CURRICULUM_VIEW = 'curriculum.view';

    public const CURRICULUM_MANAGE = 'curriculum.manage';

    public const AUDIT_VIEW = 'audit.view';

    public const AUDIT_MANAGE = 'audit.manage';

    public const SECURITY_MANAGE_USERS = 'security.manage_users';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::STUDENTS_VIEW,
            self::STUDENTS_CREATE,
            self::STUDENTS_UPDATE,
            self::STUDENTS_VIEW_PII,
            self::ENROLLMENT_VIEW,
            self::ENROLLMENT_CREATE,
            self::ENROLLMENT_UPDATE,
            self::ENROLLMENT_CANCEL,
            self::GRADES_VIEW,
            self::GRADES_CREATE,
            self::GRADES_CORRECT,
            self::GRADES_VOID,
            self::GRADES_FINALIZE,
            self::EXAM_CREATE,
            self::EXAM_UPDATE,
            self::EXAM_CANCEL,
            self::EXAM_SESSION_CREATE,
            self::EXAM_SESSION_UPDATE,
            self::EXAM_SESSION_OPEN,
            self::EXAM_SESSION_CLOSE,
            self::EXAM_ENROLLMENT_CREATE,
            self::EXAM_ENROLLMENT_UPDATE,
            self::EXAM_ENROLLMENT_CANCEL,
            self::EXAM_ENROLLMENT_PRESENT,
            self::ATTENDANCE_VIEW,
            self::ATTENDANCE_SESSION_CREATE,
            self::ATTENDANCE_MARK,
            self::ATTENDANCE_CORRECT,
            self::ATTENDANCE_SESSION_CLOSE,
            self::ATTENDANCE_SESSION_CANCEL,
            self::TIMETABLE_SCHEDULE_CREATE,
            self::TIMETABLE_VIEW,
            self::TIMETABLE_SCHEDULE_UPDATE,
            self::TIMETABLE_SCHEDULE_CANCEL,
            self::TIMETABLE_EXCEPTION_CREATE,
            self::TIMETABLE_EXCEPTION_UPDATE,
            self::RESULTS_VIEW,
            self::RESULTS_CALCULATE,
            self::RESULTS_FINALIZE,
            self::RESULTS_RANKING_BUILD,
            self::RESULTS_TRANSCRIPT_ISSUE,
            self::RESULTS_REBUILD,
            self::PORTAL_RESULTS_VIEW,
            self::PORTAL_SCOPES_MANAGE,
            self::TEACHERS_VIEW,
            self::TEACHERS_MANAGE,
            self::PROMOTION_VIEW,
            self::PROMOTION_MANAGE,
            self::TRANSFERS_VIEW,
            self::TRANSFERS_MANAGE,
            self::DOCUMENTS_VIEW,
            self::DOCUMENTS_MANAGE,
            self::FINANCE_VIEW,
            self::FINANCE_MANAGE,
            self::COMMUNICATION_VIEW,
            self::COMMUNICATION_MANAGE,
            self::WORKFLOW_VIEW,
            self::WORKFLOW_MANAGE,
            self::WORKFLOW_DECIDE,
            self::HR_VIEW,
            self::HR_MANAGE,
            self::HR_PAYROLL_VIEW,
            self::HR_PAYROLL_MANAGE,
            self::VOCATIONAL_MANAGE,
            self::VOCATIONAL_VIEW,
            self::CURRICULUM_VIEW,
            self::CURRICULUM_MANAGE,
            self::AUDIT_VIEW,
            self::AUDIT_MANAGE,
            self::SECURITY_MANAGE_USERS,
        ];
    }
}
