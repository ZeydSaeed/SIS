import { router } from '@inertiajs/react';
import { RotateCcw, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendancePage,
    PageNotice,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useYearFilterSpec,
    visitWith,
} from '@/components/attendance/attendance-chrome';
import {
    newKey,
    type AttendanceAbilities,
} from '@/components/attendance/attendance-ui';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Policy = {
    id: number | null;
    version_no: number;
    timezone: string;
    open_before_minutes: number;
    submit_deadline_minutes: number;
    edit_window_days: number;
    late_grace_minutes: number;
    late_as_absent_minutes: number | null;
    require_complete: boolean;
    default_mark: number;
    teacher_can_mark_excused: boolean;
    absence_alert_count: number;
    absence_alert_window_days: number;
    absence_rate_alert_percent: number;
    late_alert_count: number;
    returned_alert_count: number;
    staff_day_start: string;
    staff_late_grace_minutes: number;
    checkin_code_ttl_minutes: number;
    qr_enabled: boolean;
    nfc_enabled: boolean;
};

type Props = {
    settings: { policy: Policy; is_default: boolean; default_timezone: string };
    filters: { academic_year_id: number };
    abilities: AttendanceAbilities;
};

const TIMEZONES = [
    'Asia/Baghdad',
    'Asia/Riyadh',
    'Asia/Kuwait',
    'Asia/Amman',
    'Asia/Damascus',
    'Asia/Beirut',
    'Asia/Dubai',
    'Asia/Aden',
    'Africa/Cairo',
    'UTC',
];

type GroupKey = 'time' | 'marking' | 'alerts' | 'staff' | 'evidence';

const GROUPS: Record<GroupKey, Array<keyof Policy>> = {
    time: [
        'timezone',
        'open_before_minutes',
        'submit_deadline_minutes',
        'edit_window_days',
    ],
    marking: [
        'late_grace_minutes',
        'late_as_absent_minutes',
        'require_complete',
        'default_mark',
        'teacher_can_mark_excused',
    ],
    alerts: [
        'absence_alert_count',
        'absence_alert_window_days',
        'absence_rate_alert_percent',
        'late_alert_count',
        'returned_alert_count',
    ],
    staff: [
        'staff_day_start',
        'staff_late_grace_minutes',
        'checkin_code_ttl_minutes',
    ],
    evidence: ['qr_enabled', 'nfc_enabled'],
};

const BOOLEANS = new Set<keyof Policy>([
    'require_complete',
    'teacher_can_mark_excused',
    'qr_enabled',
    'nfc_enabled',
]);

export default function AttendanceSettings(props: Props) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.settings.title, href: '/attendance/settings' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsPage {...props} />
        </AppLayout>
    );
}

function SettingsPage({ settings, filters, abilities }: Props) {
    const a = t().attendanceSystem;
    const st = a.settings;
    const { showInertiaErrors } = usePageError();
    const [form, setForm] = useState<Policy>(settings.policy);
    const [busy, setBusy] = useState(false);
    const editable = abilities.manage_policy;
    const dirty = JSON.stringify(form) !== JSON.stringify(settings.policy);
    const zones = TIMEZONES.includes(form.timezone)
        ? TIMEZONES
        : [form.timezone, ...TIMEZONES];

    const save = () => {
        const { id: _id, version_no: _v, ...payload } = form;
        setBusy(true);
        router.post('/attendance/settings', payload, {
            preserveScroll: true,
            headers: { 'X-Idempotency-Key': newKey('policy') },
            onError: (errors) => showInertiaErrors(errors),
            onFinish: () => setBusy(false),
        });
    };

    const editGroups = useMemo(
        (): PageRibbonGroup[] =>
            editable
                ? [
                      {
                          id: 'attendance-settings-actions',
                          label: a.chrome.actions,
                          commands: [
                              {
                                  id: 'attendance-settings-save',
                                  label: st.save,
                                  icon: Save,
                                  iconTone: 'ok',
                                  disabled: busy || !dirty,
                                  onSelect: save,
                              },
                              {
                                  id: 'attendance-settings-reset',
                                  label: st.discard,
                                  icon: RotateCcw,
                                  iconTone: 'steel',
                                  disabled: busy || !dirty,
                                  onSelect: () => setForm(settings.policy),
                              },
                          ],
                      },
                  ]
                : [],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [editable, busy, dirty, form, settings.policy],
    );
    useAttendanceEditRibbon('settings', abilities, editGroups);
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/attendance/settings', { academic_year_id: year }),
    );
    useAttendanceHomeFilters([yearSpec], null, [
        filters.academic_year_id,
        yearSpec.options.length,
    ]);

    const field = (key: keyof Policy) => {
        const label = st.fields[key] ?? key;
        const value = form[key];
        if (key === 'timezone') {
            return (
                <RegistryListField
                    key={key}
                    label={label}
                    editing={editable}
                    required
                    value={form.timezone}
                    display={form.timezone}
                    options={zones.map((z) => ({ value: z, label: z }))}
                    onChange={(timezone) => setForm({ ...form, timezone })}
                />
            );
        }
        if (key === 'default_mark') {
            return (
                <RegistryListField
                    key={key}
                    label={label}
                    editing={editable}
                    required
                    fieldClassName="sis-branches-field--wide"
                    value={String(form.default_mark)}
                    display={st.defaultMarks[form.default_mark]}
                    options={[0, 1].map((v) => ({
                        value: String(v),
                        label: st.defaultMarks[v],
                    }))}
                    onChange={(v) =>
                        setForm({ ...form, default_mark: Number(v) })
                    }
                />
            );
        }
        if (BOOLEANS.has(key)) {
            return (
                <RegistryListField
                    key={key}
                    label={label}
                    editing={editable}
                    required
                    value={value ? '1' : '0'}
                    display={value ? st.yes : st.no}
                    options={[
                        { value: '1', label: st.yes },
                        { value: '0', label: st.no },
                    ]}
                    onChange={(v) => setForm({ ...form, [key]: v === '1' })}
                />
            );
        }
        if (key === 'staff_day_start') {
            return (
                <label key={key} className="sis-admission-sheet__field">
                    <span className="sis-admission-sheet__label">{label}</span>
                    <input
                        className="sis-admission-sheet__control sis-admission-draft-field--filled"
                        type="time"
                        dir="ltr"
                        disabled={!editable}
                        value={form.staff_day_start}
                        onChange={(event) =>
                            setForm({
                                ...form,
                                staff_day_start: event.target.value,
                            })
                        }
                    />
                </label>
            );
        }

        return (
            <RegistryTextField
                key={key}
                label={label}
                editing={editable}
                required={key !== 'late_as_absent_minutes'}
                type="number"
                dir="ltr"
                value={value === null ? '' : String(value)}
                onChange={(v) =>
                    setForm({
                        ...form,
                        [key]:
                            v === ''
                                ? key === 'late_as_absent_minutes'
                                    ? null
                                    : 0
                                : Number(v),
                    })
                }
            />
        );
    };

    return (
        <AttendancePage
            title={st.title}
            scroll
            notices={
                <>
                    <PageNotice>{st.description}</PageNotice>
                    <PageNotice>
                        {settings.is_default
                            ? st.isDefault
                            : st.version.replace(
                                  ':v',
                                  String(settings.policy.version_no),
                              )}
                    </PageNotice>
                    {!editable ? <PageNotice>{st.readOnly}</PageNotice> : null}
                </>
            }
        >
            <div className="sis-branches-sheet">
                {(Object.keys(GROUPS) as GroupKey[]).map((group) => (
                    <SheetSection
                        key={group}
                        id={`att-settings-${group}`}
                        title={(st.groups as Record<GroupKey, string>)[group]}
                    >
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            {GROUPS[group].map(field)}
                        </div>
                    </SheetSection>
                ))}
            </div>
        </AttendancePage>
    );
}
