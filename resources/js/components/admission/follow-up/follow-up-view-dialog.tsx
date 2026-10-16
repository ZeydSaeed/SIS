import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ExternalLink } from 'lucide-react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { documentTypeLabel } from '@/components/admission/admission-document-types';
import {
    CaptionCount,
    type FollowUpApplication,
} from '@/components/admission/follow-up/follow-up-show';
import { applicationStatusLabel } from '@/components/admission/follow-up/follow-up-transitions';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { direction, t } from '@/i18n';

type Props = {
    /** Null while the selection is being read. */
    applications: FollowUpApplication[] | null;
    canViewPii: boolean;
    showHref: (application: FollowUpApplication) => string;
    onClose: () => void;
};

/** «2026-09-05 10:12» — fixed digits and order inside right-to-left text. */
function when(value: string | null): string {
    if (value === null || value === '') {
        return '—';
    }
    const date = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** A read-only sheet field (the student sheet's display control). */
function Field({
    label,
    value,
    dir = 'rtl',
    wide = false,
}: {
    label: string;
    value: ReactNode;
    dir?: 'rtl' | 'ltr';
    wide?: boolean;
}) {
    const filled = value !== null && value !== undefined && value !== '';

    return (
        <div
            className={`sis-admission-sheet__field${wide ? ' sis-follow-up-sheet__wide' : ''}`}
        >
            <span className="sis-admission-sheet__label">{label}</span>
            <div
                className={`sis-admission-sheet__control sis-admission-draft-readonly${filled ? ' sis-admission-draft-field--filled' : ''}`}
                dir={dir}
                aria-readonly="true"
            >
                {filled ? value : '—'}
            </div>
        </div>
    );
}

/** A sheet section holding a table: the same banner, a plain body (the field grid is for fields only). */
function TableSection({
    id,
    title,
    count,
    children,
}: {
    id: string;
    title: string;
    /** The rows of the table, shown beside the title. */
    count: number;
    children: ReactNode;
}) {
    return (
        <section className="sis-admission-sheet__section" aria-labelledby={id}>
            <h3
                id={id}
                className="sis-admission-sheet__banner sis-admission-sheet__banner--accent sis-follow-up-sheet__table-title"
            >
                <span>{title}</span>
                <CaptionCount count={count} />
            </h3>
            <div className="sis-follow-up-sheet__table-body">{children}</div>
        </section>
    );
}

/** One application as a sheet: applicant, request, documents and history — sized to show without scrolling. */
function ApplicationSheet({
    application,
    canViewPii,
}: {
    application: FollowUpApplication;
    canViewPii: boolean;
}) {
    const a = t().admission;
    const fullName = [
        application.first_name,
        application.father_name,
        application.grandfather_name,
        application.great_grandfather_name,
        application.last_name,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <>
            <SheetSection
                id={`follow-up-applicant-${application.id}`}
                title={a.followUpApplicantData}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <Field
                        label={a.applicationNumber}
                        value={application.application_number}
                        dir="ltr"
                    />
                    <Field
                        label={t().students.quadName}
                        value={fullName}
                        wide
                    />
                    <Field
                        label={t().common.status}
                        value={applicationStatusLabel(application.status)}
                    />
                    <Field
                        label={a.motherName}
                        value={application.mother_name}
                    />
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <Field
                        label={a.maternalFatherName}
                        value={application.maternal_father_name}
                    />
                    <Field
                        label={a.maternalGrandfatherName}
                        value={application.maternal_grandfather_name}
                    />
                    <Field
                        label={a.nationalId}
                        value={
                            canViewPii
                                ? application.national_id
                                : a.followUpPiiHidden
                        }
                        dir="ltr"
                    />
                    <Field
                        label={a.birthDate}
                        value={application.birth_date}
                        dir="ltr"
                    />
                    <Field
                        label={a.birthPlace}
                        value={application.birth_place}
                    />
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <Field
                        label={a.gender}
                        value={
                            application.gender === 2
                                ? a.genderFemale
                                : a.genderMale
                        }
                    />
                    <Field
                        label={a.governorate}
                        value={application.governorate}
                    />
                    <Field
                        label={a.neighborhood}
                        value={application.neighborhood}
                    />
                </div>
            </SheetSection>
            <SheetSection
                id={`follow-up-request-${application.id}`}
                title={a.followUpRequestData}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <Field
                        label={a.followUpChannel}
                        value={
                            application.request_kind === 1
                                ? a.followUpChannelTransfer
                                : a.followUpChannelVocational
                        }
                    />
                    <Field
                        label={a.filterBySchool}
                        value={application.school_name}
                    />
                    <Field
                        label={a.academicYear}
                        value={application.academic_year_name}
                        dir="ltr"
                    />
                    <Field
                        label={a.followUpPeriod}
                        value={application.period_name}
                    />
                    <Field
                        label={a.followUpSubmittedAt}
                        value={when(application.submitted_at)}
                        dir="ltr"
                    />
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <Field
                        label={a.gradeLevel}
                        value={application.grade_name}
                    />
                    <Field label={a.branch} value={application.branch_name} />
                    <Field
                        label={a.department}
                        value={
                            application.department_name ??
                            application.specialization_name
                        }
                    />
                    <Field
                        label={a.followUpReviewer}
                        value={application.reviewer_name}
                    />
                    <Field
                        label={a.followUpReason}
                        value={
                            application.rejection_reason ??
                            application.withdrawal_reason ??
                            application.notes
                        }
                    />
                </div>
            </SheetSection>
            <div className="sis-follow-up-sheet__pair">
                <TableSection
                    id={`follow-up-documents-${application.id}`}
                    title={a.followUpDocuments}
                    count={application.documents.length}
                >
                    {application.documents.length === 0 ? (
                        <p className="sis-follow-up-muted">
                            {a.followUpNoDocuments}
                        </p>
                    ) : (
                        <div className="sis-follow-up-table sis-follow-up-table--compact">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">{a.documentType}</th>
                                        <th scope="col">{a.fileName}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {application.documents.map((document) => (
                                        <tr key={document.id}>
                                            <td>
                                                {documentTypeLabel(
                                                    document.document_type,
                                                )}
                                            </td>
                                            <td dir="ltr">
                                                {document.file_name}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </TableSection>
                <TableSection
                    id={`follow-up-history-${application.id}`}
                    title={a.followUpHistory}
                    count={application.history.length + 1}
                >
                    <div className="sis-follow-up-table sis-follow-up-table--compact">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">{a.followUpEvent}</th>
                                    <th scope="col">{a.followUpWhen}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{a.followUpCreated}</td>
                                    <td dir="ltr">
                                        {when(application.created_at)}
                                    </td>
                                </tr>
                                {application.history.map((event, index) => (
                                    <tr key={index}>
                                        <td>
                                            {applicationStatusLabel(
                                                event.from_status,
                                            )}{' '}
                                            ←{' '}
                                            {applicationStatusLabel(
                                                event.to_status,
                                            )}
                                        </td>
                                        <td>
                                            <bdi dir="ltr">
                                                {when(event.at)}
                                            </bdi>
                                            {event.by ? ` · ${event.by}` : ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </TableSection>
            </div>
        </>
    );
}

/**
 * «عرض» of the follow-up list — the project's sheet window (drag, maximize, close, hero with the logo, as the
 * students «عرض»). Several applications show one at a time; «السابق» / «التالي» (or ← →) slide between them,
 * so nothing scrolls.
 */
export function FollowUpViewDialog({
    applications,
    canViewPii,
    showHref,
    onClose,
}: Props) {
    const i18n = t();
    const a = i18n.admission;
    const [index, setIndex] = useState(0);
    const [motion, setMotion] = useState<'next' | 'previous' | null>(null);
    const { contentRef, bringToFront, heroDragProps, resizeHandles } =
        useSmoothDialogDrag(true, {
            resizable: true,
            minSize: { width: 640, height: 360 },
        });
    const { maximized, toggleMaximize, maximizeClassName } =
        useSheetMaximize(contentRef);
    const count = applications?.length ?? 0;
    const current =
        applications?.[Math.min(index, Math.max(0, count - 1))] ?? null;

    const position = Math.min(index, Math.max(0, count - 1));
    const go = useCallback(
        (step: 1 | -1) => {
            const next = position + step;
            if (next < 0 || next >= count) {
                return;
            }
            setMotion(step === 1 ? 'next' : 'previous');
            setIndex(next);
        },
        [count, position],
    );

    // ← / → move between the applications (the arrows follow the reading direction).
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (
                event.target instanceof HTMLInputElement ||
                event.target instanceof HTMLTextAreaElement
            ) {
                return;
            }
            const forward = direction() === 'rtl' ? 'ArrowLeft' : 'ArrowRight';
            const back = direction() === 'rtl' ? 'ArrowRight' : 'ArrowLeft';
            if (event.key === forward) {
                go(1);
            } else if (event.key === back) {
                go(-1);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [go]);

    const Back = direction() === 'rtl' ? ChevronRight : ChevronLeft;
    const Forward = direction() === 'rtl' ? ChevronLeft : ChevronRight;
    const title = count > 1 ? `${a.followUpView} (${count})` : a.followUpView;

    return (
        <Dialog
            open
            modal={false}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                ref={contentRef}
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog sis-student-info-sheet sis-window--movable sis-follow-up-sheet${maximizeClassName}`}
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                {maximized ? null : resizeHandles}
                <DialogTitle className="sr-only">{title}</DialogTitle>
                <div className="sis-admission-draft-form sis-admission-sheet sis-follow-up-sheet__sheet">
                    <header
                        className="sis-admission-sheet__hero"
                        {...(maximized ? {} : heroDragProps)}
                    >
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable
                            maximized={maximized}
                            onMaximize={toggleMaximize}
                            onClose={onClose}
                        />
                        <div className="sis-admission-sheet__hero-copy">
                            <p className="sis-admission-sheet__hero-title">
                                {title}
                            </p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo
                                tone="on-dark"
                                className="sis-admission-sheet__logo"
                            />
                        </div>
                    </header>

                    <div className="sis-follow-up-sheet__stage">
                        {current === null ? (
                            <p
                                className="sis-admission-sheet__empty"
                                aria-busy={applications === null}
                            >
                                {applications === null
                                    ? i18n.common.loading
                                    : a.followUpEmptyList}
                            </p>
                        ) : (
                            <div
                                key={current.id}
                                className={`sis-follow-up-sheet__page${motion ? ` sis-follow-up-sheet__page--${motion}` : ''}`}
                            >
                                <ApplicationSheet
                                    application={current}
                                    canViewPii={canViewPii}
                                />
                            </div>
                        )}
                    </div>

                    <div className="sis-admission-sheet__actions sis-follow-up-sheet__actions">
                        {current !== null ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.visit(showHref(current))}
                            >
                                <ExternalLink className="size-4" aria-hidden />
                                {a.followUpView}
                            </Button>
                        ) : null}
                        {count > 1 ? (
                            <div className="sis-follow-up-sheet__pager">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label={i18n.common.previous}
                                    disabled={position <= 0}
                                    onClick={() => go(-1)}
                                >
                                    <Back className="size-4" aria-hidden />
                                </Button>
                                <span
                                    className="sis-follow-up-sheet__position"
                                    dir="ltr"
                                >
                                    {position + 1} / {count}
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label={i18n.common.next}
                                    disabled={position >= count - 1}
                                    onClick={() => go(1)}
                                >
                                    <Forward className="size-4" aria-hidden />
                                </Button>
                            </div>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {i18n.dialog.close}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
