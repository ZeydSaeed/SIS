import { Head, router } from '@inertiajs/react';
import {
    Archive,
    ArrowLeftRight,
    CheckCircle2,
    CircleSlash,
    Eye,
    Layers,
    Save,
    XCircle,
    Building2,
    ChevronDown,
    ChevronUp,
    GitBranch,
    Landmark,
    MoreVertical,
    Pencil,
    Plus,
    PlusCircle,
    RotateCcw,
    Search,
    Trash2,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { useRegisterPageRibbon, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { usePageError } from '@/components/sis/page-error-context';
import {
    blankToNull,
    RegistryListField,
    RegistrySheetDialog,
    RegistryTextField,
    useRegistryRequest,
} from '@/components/organization/registry-sheet';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Department = { id: number; name: string };
type Branch = { id: number; name: string; departments: Department[] };
type School = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    status: number;
    branches: Branch[];
};
type Directorate = { id: number; name: string; region: string | null; status: number; schools: School[] };

type Props = {
    directorates: Directorate[];
    current_school_id: number | null;
    focus: { school_id: number | null; edit: boolean };
    authorization: { can_manage_schools: boolean; can_manage_directorates: boolean };
};

type SchoolDraft = {
    id: number | null;
    status: string;
    /** «عرض» from the ribbon: read-only until «تعديل». */
    viewOnly?: boolean;
    name: string;
    directorate_id: string;
    phone: string;
    email: string;
    address: string;
};

const ACTIVE = 1;

/** 1 نشط · 2 غير نشط · 3 مؤرشف (schools, directorates, branches, departments). */
function statusOptions(): Array<{ value: string; label: string }> {
    const r = t().orgRibbon;

    return [
        { value: '1', label: r.statusActive },
        { value: '2', label: r.statusInactive },
        { value: '3', label: r.statusArchived },
    ];
}

function statusLabel(status: number): string {
    return statusOptions().find((option) => option.value === String(status))?.label ?? String(status);
}

function StatusPill({ status }: { status: number }) {
    const tone = status === 1 ? '' : status === 3 ? ' sis-org-status--archived' : ' sis-org-status--inactive';

    return <span className={`sis-branches-status${tone}`}>{statusLabel(status)}</span>;
}

/** Status route: reactivate / deactivate / archive. */
function statusAction(status: number): string {
    return status === 1 ? 'reactivate' : status === 3 ? 'archive' : 'deactivate';
}

/** The school switcher (schoolContext) lists new / renamed schools too. */
const RELOAD_PROPS = ['directorates', 'schoolContext', 'flash'];

function departmentCount(school: School): number {
    return school.branches.reduce((total, branch) => total + branch.departments.length, 0);
}

function DirectorateTile({ active, large = false }: { active: boolean; large?: boolean }) {
    return (
        <span
            className={`sis-branches-tile sis-branches-tile--${active ? 'industrial' : 'default'}${large ? ' sis-branches-tile--lg' : ''}`}
            aria-hidden="true"
        >
            <Landmark />
        </span>
    );
}


export default function OrganizationDirectoratesSchools(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().directorateSchools.title, href: '/organization/directorates-schools' }];

    // Inner component: page error / flash contexts live inside AppLayout.
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <DirectoratesSchoolsPage {...props} />
        </AppLayout>
    );
}

function initialDirectorateId(directorates: Directorate[], schoolIds: Array<number | null>): number | null {
    for (const schoolId of schoolIds) {
        const owner = directorates.find((item) => item.schools.some((school) => school.id === schoolId));
        if (owner) {
            return owner.id;
        }
    }

    return directorates[0]?.id ?? null;
}

function DirectoratesSchoolsPage({ directorates, current_school_id: currentSchoolId, focus, authorization }: Props) {
    const i18n = t();
    const d = i18n.directorateSchools;
    const canManageDirectorates = authorization.can_manage_directorates;
    const canManageSchools = authorization.can_manage_schools;
    const request = useRegistryRequest(RELOAD_PROPS);
    const { showInertiaErrors } = usePageError();

    const [selectedId, setSelectedId] = useState<number | null>(() =>
        initialDirectorateId(directorates, [focus.school_id, currentSchoolId]),
    );
    const [collapsed, setCollapsed] = useState(true);
    const [directorateName, setDirectorateName] = useState('');
    const [directorateRegion, setDirectorateRegion] = useState('');
    const [saving, setSaving] = useState(false);
    const [expandedSchools, setExpandedSchools] = useState<number[]>(() =>
        focus.school_id !== null ? [focus.school_id] : [],
    );

    const [addDirectorateOpen, setAddDirectorateOpen] = useState(false);
    const [newDirectorate, setNewDirectorate] = useState({ name: '', region: '', status: '1' });
    const [directorateStatus, setDirectorateStatus] = useState('1');
    const [schoolDraft, setSchoolDraft] = useState<SchoolDraft | null>(null);
    const [manageOpen, setManageOpen] = useState(false);
    const [manageDirectorateId, setManageDirectorateId] = useState('');
    const [manageSearch, setManageSearch] = useState('');
    const [manageChecked, setManageChecked] = useState<number[]>([]);
    const [deleteDirectorate, setDeleteDirectorate] = useState<Directorate | null>(null);
    const [deleteSchool, setDeleteSchool] = useState<School | null>(null);
    /** Ids before «إضافة مديرية» — the new one is selected after the reload. */
    const knownDirectoratesRef = useRef<Set<number> | null>(null);

    const selected = directorates.find((item) => item.id === selectedId) ?? null;
    /** A school row of the selected directorate; when set, the «تحرير» actions target it. */
    const [selectedSchoolId, setSelectedSchoolId] = useState<number | null>(null);
    const selectedSchool = selected?.schools.find((school) => school.id === selectedSchoolId) ?? null;
    useEffect(() => {
        setSelectedSchoolId(null);
    }, [selectedId]);
    const allSchools = useMemo(
        () => directorates.flatMap((item) => item.schools.map((school) => ({ ...school, directorate: item }))),
        [directorates],
    );
    const activeDirectorates = directorates.filter((item) => item.status === ACTIVE);

    // Keep a valid selection after reloads; select a directorate that was just added.
    useEffect(() => {
        const known = knownDirectoratesRef.current;
        const created = known === null ? undefined : directorates.find((item) => !known.has(item.id));
        if (created) {
            knownDirectoratesRef.current = null;
            setSelectedId(created.id);

            return;
        }
        if (selectedId === null || !directorates.some((item) => item.id === selectedId)) {
            setSelectedId(directorates[0]?.id ?? null);
        }
    }, [directorates, selectedId]);

    useEffect(() => {
        setDirectorateName(selected?.name ?? '');
        setDirectorateRegion(selected?.region ?? '');
        setDirectorateStatus(String(selected?.status ?? 1));
    }, [selected?.status, selected?.id, selected?.name, selected?.region]);

    // Edit → تعديل المدرسة (admission page): open that school's form once.
    const focusHandledRef = useRef(false);
    useEffect(() => {
        if (focusHandledRef.current || !focus.edit || focus.school_id === null || !canManageSchools) {
            return;
        }
        focusHandledRef.current = true;
        const target = allSchools.find((school) => school.id === focus.school_id);
        if (target) {
            openEditSchool(target, target.directorate.id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const directorateDirty =
        selected !== null &&
        (directorateName.trim() !== selected.name || blankToNull(directorateRegion) !== (selected.region ?? null)) || (selected !== null && Number(directorateStatus) !== selected.status);

    const run = async (action: () => Promise<boolean>, after?: () => void): Promise<void> => {
        if (saving) {
            return;
        }
        setSaving(true);
        try {
            if (await action()) {
                after?.();
            }
        } finally {
            setSaving(false);
        }
    };

    const saveDirectorate = () =>
        selected &&
        run(async () => {
            const fieldsChanged = directorateName.trim() !== selected.name || blankToNull(directorateRegion) !== (selected.region ?? null);
            if (
                fieldsChanged
                && !(await request('patch', `/organization/directorates/${selected.id}`, {
                    name: directorateName.trim(),
                    region: blankToNull(directorateRegion),
                }))
            ) {
                return false;
            }
            const status = Number(directorateStatus);

            return status === selected.status
                ? true
                : request('post', `/organization/directorates/${selected.id}/${statusAction(status)}`);
        });

    const createDirectorate = () =>
        run(
            async () => {
                knownDirectoratesRef.current = new Set(directorates.map((item) => item.id));
                const created = await request('post', '/organization/directorates', {
                    name: newDirectorate.name.trim(),
                    region: blankToNull(newDirectorate.region),
                    status: Number(newDirectorate.status),
                });
                if (!created) {
                    knownDirectoratesRef.current = null;
                }

                return created;
            },
            () => {
                setAddDirectorateOpen(false);
                setNewDirectorate({ name: '', region: '', status: '1' });
            },
        );

    const openAddSchool = (directorateId: number | null) => {
        const target = directorates.find((item) => item.id === directorateId && item.status === ACTIVE) ?? activeDirectorates[0];
        setSchoolDraft({
            id: null,
            status: '1',
            name: '',
            directorate_id: target ? String(target.id) : '',
            phone: '',
            email: '',
            address: '',
        });
    };

    function openEditSchool(school: School, directorateId: number, viewOnly = false) {
        setSchoolDraft({
            id: school.id,
            status: String(school.status),
            viewOnly,
            name: school.name,
            directorate_id: String(directorateId),
            phone: school.phone ?? '',
            email: school.email ?? '',
            address: school.address ?? '',
        });
    }

    const editedSchool = schoolDraft?.id != null ? (allSchools.find((school) => school.id === schoolDraft.id) ?? null) : null;

    const saveSchool = () => {
        if (schoolDraft === null) {
            return;
        }
        const payload = {
            name: schoolDraft.name.trim(),
            directorate_id: Number(schoolDraft.directorate_id),
            phone: blankToNull(schoolDraft.phone),
            email: blankToNull(schoolDraft.email),
            address: blankToNull(schoolDraft.address),
        };
        const status = Number(schoolDraft.status);
        if (schoolDraft.id === null) {
            void run(() => request('post', '/organization/schools', { ...payload, status }), () => setSchoolDraft(null));

            return;
        }
        if (editedSchool === null) {
            return;
        }
        // Only changed fields — an unchanged (possibly inactive) directorate is not re-validated.
        const changed: Record<string, string | number | null> = {};
        if (payload.name !== editedSchool.name) changed.name = payload.name;
        if (payload.directorate_id !== editedSchool.directorate.id) changed.directorate_id = payload.directorate_id;
        if (payload.phone !== editedSchool.phone) changed.phone = payload.phone;
        if (payload.email !== editedSchool.email) changed.email = payload.email;
        if (payload.address !== editedSchool.address) changed.address = payload.address;
        const statusChanged = status !== editedSchool.status;
        if (Object.keys(changed).length === 0 && !statusChanged) {
            setSchoolDraft(null);

            return;
        }
        void run(
            async () => {
                if (Object.keys(changed).length > 0 && !(await request('patch', `/organization/schools/${editedSchool.id}`, changed))) {
                    return false;
                }

                return statusChanged ? request('post', `/organization/schools/${editedSchool.id}/${statusAction(status)}`) : true;
            },
            () => setSchoolDraft(null),
        );
    };

    /** 1 نشط · 2 غير نشط · 3 مؤرشف («حذف» archives). */
    const setStatus = (kind: 'schools' | 'directorates', id: number, status: number, after?: () => void) =>
        run(() => request('post', `/organization/${kind}/${id}/${statusAction(status)}`), after);

    const toggleSchool = (schoolId: number) =>
        setExpandedSchools((current) =>
            current.includes(schoolId) ? current.filter((id) => id !== schoolId) : [...current, schoolId],
        );

    // «الفروع والاختصاصات» works on the current school: switch to this school, then open it.
    const openBranches = (schoolId: number) => {
        if (schoolId === currentSchoolId) {
            router.visit('/organization/branches');

            return;
        }
        router.post(
            '/context/school',
            { school_id: schoolId },
            {
                preserveScroll: true,
                onSuccess: () => router.visit('/organization/branches'),
                onError: (errors) => showInertiaErrors(errors, i18n.errors.saveFailed),
            },
        );
    };

    const openManage = (directorateId: number) => {
        setManageDirectorateId(String(directorateId));
        setManageSearch('');
        setManageChecked([]);
        setManageOpen(true);
    };

    const manageDirectorate = directorates.find((item) => String(item.id) === manageDirectorateId) ?? null;
    const manageInIds = manageDirectorate?.schools.map((school) => school.id) ?? [];
    const manageSchools = allSchools.filter(
        (school) => manageSearch.trim() === '' || school.name.includes(manageSearch.trim()),
    );
    const manageMovable = manageSchools.filter((school) => !manageInIds.includes(school.id));
    const manageCanMove = manageDirectorate !== null && manageDirectorate.status === ACTIVE;

    const moveSchools = () =>
        manageDirectorate &&
        run(
            () =>
                request('patch', `/organization/directorates/${manageDirectorate.id}`, {
                    school_ids: [...manageInIds, ...manageChecked],
                }),
            () => setManageChecked([]),
        );

    const directorateOptions = (keepId: number | null) =>
        directorates
            .filter((item) => item.status === ACTIVE || item.id === keepId)
            .map((item) => ({ value: String(item.id), label: item.name }));
    const directorateListOptions = directorates.map((item) => ({ value: String(item.id), label: item.name }));

    const selectedActive = selected?.status === ACTIVE;

    // «تحرير» ribbon (same mechanism as the students page): actions on the selected directorate,
    // status tabs with counts, management commands, completion; titlebar search filters the list.
    const r = i18n.orgRibbon;
    const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive' | 'archived'>('all');
    const [query, setQuery] = useState('');
    const activeCount = activeDirectorates.length;
    const visibleDirectorates = useMemo(() => {
        const q = query.trim();

        return directorates.filter((item) => {
            if (statusFilter === 'active' && item.status !== ACTIVE) return false;
            if (statusFilter === 'inactive' && item.status !== 2) return false;
            if (statusFilter === 'archived' && item.status !== 3) return false;

            return q === '' || item.name.includes(q) || item.schools.some((school) => school.name.includes(q));
        });
    }, [directorates, query, statusFilter]);
    const schoolsWithBranches = allSchools.filter((school) => school.branches.length > 0).length;
    const schoolsPercent = allSchools.length === 0 ? 0 : Math.round((schoolsWithBranches / allSchools.length) * 100);

    const schoolEditable = schoolDraft !== null && schoolDraft.viewOnly !== true
        && schoolDraft.name.trim() !== '' && schoolDraft.directorate_id !== '';
    // A selected school is managed with the school permission; a directorate with the directorate one.
    const canEditTarget = selectedSchool !== null ? canManageSchools : canManageDirectorates;
    const targetActive = selectedSchool !== null ? selectedSchool.status === ACTIVE : selectedActive;
    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const noSelection = selected === null;
        const groups: PageRibbonGroup[] = [
            {
                id: 'org-directorate-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'org-directorate-view',
                        label: i18n.common.view,
                        icon: Eye,
                        title: noSelection ? r.needsSelection : i18n.common.view,
                        disabled: noSelection,
                        onSelect: () => {
                            if (selectedSchool !== null && selected !== null) {
                                openEditSchool(selectedSchool, selected.id, true);

                                return;
                            }
                            setCollapsed(false);
                        },
                    },
                    ...(canEditTarget
                        ? [
                              {
                                  id: 'org-directorate-edit',
                                  label: i18n.common.edit,
                                  icon: Pencil,
                                  tone: 'edit' as const,
                                  disabled: noSelection || saving,
                                  onSelect: () => {
                                      if (selectedSchool !== null && selected !== null) {
                                          openEditSchool(selectedSchool, selected.id);

                                          return;
                                      }
                                      setCollapsed(false);
                                  },
                              },
                              {
                                  id: 'org-directorate-save',
                                  label: i18n.common.save,
                                  icon: Save,
                                  tone: 'save' as const,
                                  disabled: saving || !(schoolEditable || (directorateDirty && directorateName.trim() !== '')),
                                  onSelect: () => {
                                      if (schoolEditable) {
                                          saveSchool();

                                          return;
                                      }
                                      void saveDirectorate();
                                  },
                              },
                          ]
                        : []),
                    {
                        id: 'org-directorate-cancel',
                        label: i18n.common.cancel,
                        icon: XCircle,
                        disabled: noSelection || saving,
                        onSelect: () => {
                            if (selectedSchoolId !== null) {
                                setSelectedSchoolId(null);

                                return;
                            }
                            setDirectorateName(selected?.name ?? '');
                            setDirectorateRegion(selected?.region ?? '');
                            setDirectorateStatus(String(selected?.status ?? 1));
                            setCollapsed(true);
                        },
                    },
                    ...(canEditTarget
                        ? [
                              {
                                  id: 'org-directorate-delete',
                                  label: i18n.common.delete,
                                  icon: Trash2,
                                  tone: 'delete' as const,
                                  disabled: noSelection || saving || !targetActive,
                                  onSelect: () => {
                                      if (selectedSchool !== null) {
                                          setDeleteSchool(selectedSchool);

                                          return;
                                      }
                                      if (selected !== null) {
                                          setDeleteDirectorate(selected);
                                      }
                                  },
                              },
                              {
                                  id: 'org-directorate-reactivate',
                                  label: r.reactivate,
                                  icon: RotateCcw,
                                  disabled: noSelection || saving || targetActive,
                                  onSelect: () => {
                                      if (selectedSchool !== null) {
                                          void setStatus('schools', selectedSchool.id, 1);

                                          return;
                                      }
                                      if (selected !== null) {
                                          void setStatus('directorates', selected.id, 1);
                                      }
                                  },
                              },
                          ]
                        : []),
                ],
            },
            {
                id: 'org-directorate-status',
                label: r.status,
                commands: [
                    { key: 'all' as const, label: r.all, icon: Layers, count: directorates.length },
                    { key: 'active' as const, label: r.active, icon: CheckCircle2, count: activeCount },
                    { key: 'inactive' as const, label: r.inactive, icon: CircleSlash, count: directorates.filter((item) => item.status === 2).length },
                    { key: 'archived' as const, label: r.archived, icon: Archive, count: directorates.filter((item) => item.status === 3).length },
                ].map((tab) => ({
                    id: `org-directorate-status-${tab.key}`,
                    label: tab.label,
                    title: `${tab.label} (${tab.count})`,
                    icon: tab.icon,
                    count: tab.count,
                    pressed: statusFilter === tab.key,
                    onSelect: () => setStatusFilter(statusFilter === tab.key && tab.key !== 'all' ? 'all' : tab.key),
                })),
            },
        ];
        const manage = [
            ...(canManageDirectorates
                ? [{ id: 'org-directorate-add', label: d.addDirectorate, icon: PlusCircle, onSelect: () => setAddDirectorateOpen(true) }]
                : []),
            ...(canManageSchools
                ? [
                      {
                          id: 'org-school-add',
                          label: d.addSchool,
                          icon: Building2,
                          disabled: activeDirectorates.length === 0,
                          onSelect: () => openAddSchool(selectedId),
                      },
                  ]
                : []),
            ...(canManageDirectorates
                ? [
                      {
                          id: 'org-schools-manage',
                          label: d.manageTitle,
                          icon: ArrowLeftRight,
                          disabled: noSelection,
                          onSelect: () => selected && openManage(selected.id),
                      },
                  ]
                : []),
        ];
        if (manage.length > 0) {
            groups.push({ id: 'org-directorate-manage', label: r.manage, commands: manage });
        }
        groups.push({
            id: 'org-directorate-progress',
            label: r.progress,
            commands: [],
            custom: (
                <div
                    className="sis-ribbon__progress-track"
                    role="progressbar"
                    aria-label={r.schoolsProgress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={schoolsPercent}
                    data-contrast={schoolsPercent >= 45 ? 'light' : 'dark'}
                    dir="rtl"
                    title={`${r.schoolsProgress}: ${schoolsPercent}%`}
                >
                    <span className="sis-ribbon__progress-fill" style={{ width: `${schoolsPercent}%` }} />
                    <span className="sis-ribbon__progress-value" dir="ltr">
                        {schoolsPercent}%
                    </span>
                </div>
            ),
        });

        return groups;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeCount, canEditTarget, canManageDirectorates, canManageSchools, directorateDirty, directorateName, directorates, i18n, saving, schoolDraft, schoolEditable, schoolsPercent, selected, selectedActive, selectedId, selectedSchool, selectedSchoolId, statusFilter, targetActive]);
    useRegisterPageRibbon('edit', editRibbonGroups);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: query,
            label: r.searchDirectorates,
            placeholder: r.searchDirectorates,
            onDraftChange: setQuery,
            onCommit: setQuery,
        }),
        [query, r.searchDirectorates],
    );
    useRegisterPageTitlebarSearch(titlebarSearch);

    return (
        <>
            <Head title={d.title} />
            <div className="sis-ops-hub sis-branches-page sis-org-page" dir="rtl" lang="ar">
                <header className="sis-branches-page__head">
                    <div className="sis-branches-page__heading">
                        <h1 className="sis-branches-page__title">{d.heading}</h1>
                    </div>
                </header>
                {canManageDirectorates || canManageSchools ? null : <p className="sis-branches-page__notice">{d.readOnly}</p>}

                <div className="sis-branches-page__grid">
                    {/* Directorates list */}
                    <section className="sis-branches-card sis-branches-list" aria-label={d.directoratesTitle}>
                        <div className="sis-branches-card__head">
                            <h2 className="sis-branches-card__title">{d.directoratesTitle}</h2>
                        </div>
                        <div className="sis-branches-table sis-org-directorates" role="group" aria-label={d.directoratesTitle}>
                            <div className="sis-branches-table__row sis-branches-table__row--head">
                                <span className="sis-org-head-title">
                                    {d.directorate}
                                    <span className="sis-branches-count" dir="ltr">
                                        {visibleDirectorates.length}
                                    </span>
                                </span>
                            </div>
                            <ul className="sis-branches-list__items">
                                {visibleDirectorates.length === 0 ? (
                                    <li className="sis-branches-empty">{d.noDirectorates}</li>
                                ) : (
                                    visibleDirectorates.map((item) => {
                                        const active = item.status === ACTIVE;

                                        return (
                                            <li key={item.id}>
                                                <div
                                                    className={`sis-branches-item${item.id === selectedId ? ' is-selected' : ''}${active ? '' : ' sis-org-item--inactive'}`}
                                                    role="button"
                                                    tabIndex={0}
                                                    aria-pressed={item.id === selectedId}
                                                    onClick={() => setSelectedId(item.id)}
                                                    onKeyDown={(event) => {
                                                        if (event.key === 'Enter' || event.key === ' ') {
                                                            event.preventDefault();
                                                            setSelectedId(item.id);
                                                        }
                                                    }}
                                                >
                                                    <DirectorateTile active={active} />
                                                    <span className="sis-branches-item__text">
                                                        <span className="sis-branches-item__name">{item.name}</span>
                                                        <span className="sis-branches-item__meta">
                                                            {item.schools.length} {d.schoolsCount}
                                                            {item.region ? ` · ${item.region}` : ''}
                                                            {active ? '' : ` · ${d.inactive}`}
                                                        </span>
                                                    </span>
                                                    {canManageDirectorates ? (
                                                        <span className="sis-branches-item__actions">
                                                            <button
                                                                type="button"
                                                                className="sis-branches-icon-btn sis-branches-icon-btn--plain"
                                                                title={d.manageTitle}
                                                                aria-label={`${d.more}: ${item.name}`}
                                                                onClick={(event) => {
                                                                    event.stopPropagation();
                                                                    openManage(item.id);
                                                                }}
                                                            >
                                                                <MoreVertical aria-hidden />
                                                            </button>
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </li>
                                        );
                                    })
                                )}
                            </ul>
                        </div>
                    </section>

                    {/* Selected directorate */}
                    <section className="sis-branches-card sis-branches-detail" aria-label={selected?.name ?? d.directoratesTitle}>
                        {selected === null ? (
                            <p className="sis-branches-empty">{d.selectDirectorate}</p>
                        ) : (
                            <>
                                <div className="sis-branches-card__head sis-branches-detail__head">
                                    <DirectorateTile active={selectedActive} large />
                                    <h2 className="sis-branches-card__title">
                                        {d.directoratePrefix} {selected.name}
                                    </h2>
                                    <StatusPill status={selected.status} />
                                    <button
                                        type="button"
                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-detail__toggle"
                                        aria-expanded={!collapsed}
                                        title={collapsed ? d.expand : d.collapse}
                                        aria-label={collapsed ? d.expand : d.collapse}
                                        onClick={() => setCollapsed((value) => !value)}
                                    >
                                        <ChevronUp aria-hidden className={collapsed ? 'is-collapsed' : undefined} />
                                    </button>
                                </div>

                                {collapsed ? null : (
                                    <div className="sis-branches-detail__form">
                                        <RegistryTextField
                                            label={d.directorateName}
                                            editing={canManageDirectorates}
                                            required
                                            value={directorateName}
                                            onChange={setDirectorateName}
                                            fieldClassName="sis-branches-field--wide"
                                        />
                                        <RegistryTextField
                                            label={d.region}
                                            editing={canManageDirectorates}
                                            value={directorateRegion}
                                            onChange={setDirectorateRegion}
                                            fieldClassName="sis-branches-field--wide"
                                        />
                                        <RegistryListField
                                            label={r.statusLabel}
                                            editing={canManageDirectorates}
                                            required
                                            value={directorateStatus}
                                            display={statusLabel(Number(directorateStatus))}
                                            options={statusOptions()}
                                            onChange={setDirectorateStatus}
                                            fieldClassName="sis-branches-field--wide"
                                        />
                                        {canManageDirectorates ? (
                                            <div className="sis-branches-detail__buttons">
                                                <Button
                                                    type="button"
                                                    disabled={!directorateDirty || saving || directorateName.trim() === ''}
                                                    onClick={() => void saveDirectorate()}
                                                >
                                                    {saving ? i18n.common.saving : d.saveChanges}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={!directorateDirty || saving}
                                                    onClick={() => {
                                                        setDirectorateName(selected.name);
                                                        setDirectorateRegion(selected.region ?? '');
                                                        setDirectorateStatus(String(selected.status));
                                                    }}
                                                >
                                                    {d.cancel}
                                                </Button>
                                            </div>
                                        ) : null}
                                    </div>
                                )}

                                <div className="sis-branches-table sis-org-schools" role="table" aria-label={d.directorateSchools}>
                                    <div className="sis-branches-table__row sis-branches-table__row--head" role="row">
                                        <span role="columnheader" className="sis-org-schools__name sis-org-head-title">
                                            {d.school}
                                            <span className="sis-branches-count" dir="ltr">
                                                {selected.schools.length}
                                            </span>
                                        </span>
                                        <span role="columnheader" className="sis-org-schools__meta">
                                            {d.branches}
                                        </span>
                                        <span role="columnheader" className="sis-branches-table__actions" />
                                    </div>
                                    {selected.schools.length === 0 ? (
                                        <div className="sis-branches-table__row" role="row">
                                            <span role="cell" className="sis-branches-empty">
                                                {d.noSchools}
                                            </span>
                                        </div>
                                    ) : (
                                        selected.schools.map((school) => {
                                            const expanded = expandedSchools.includes(school.id);
                                            const schoolActive = school.status === ACTIVE;
                                            const panelId = `org-school-branches-${school.id}`;

                                            return (
                                                <Fragment key={school.id}>
                                                    <div
                                                        className={`sis-branches-table__row sis-branches-table__row--selectable${expanded ? ' is-expanded' : ''}${
                                                            school.id === selectedSchoolId ? ' is-selected' : ''
                                                        }${schoolActive ? '' : ' sis-org-item--inactive'}`}
                                                        role="row"
                                                        tabIndex={0}
                                                        aria-selected={school.id === selectedSchoolId}
                                                        onClick={() => setSelectedSchoolId(school.id)}
                                                        onKeyDown={(event) => {
                                                            if (event.target === event.currentTarget && (event.key === 'Enter' || event.key === ' ')) {
                                                                event.preventDefault();
                                                                setSelectedSchoolId(school.id);
                                                            }
                                                        }}
                                                    >
                                                        <span role="cell" className="sis-branches-table__name sis-org-schools__name">
                                                            <Building2 aria-hidden className="sis-branches-table__icon" />
                                                            <span>{school.name}</span>
                                                            {school.id === currentSchoolId ? (
                                                                <span className="sis-org-tag">{d.current}</span>
                                                            ) : null}
                                                            {schoolActive ? null : <span className="sis-org-tag sis-org-tag--muted">{d.inactive}</span>}
                                                        </span>
                                                        <span role="cell" className="sis-org-schools__meta">
                                                            <span dir="ltr">{school.branches.length}</span> {d.branchesCount} ·{' '}
                                                            <span dir="ltr">{departmentCount(school)}</span> {d.departmentsCount}
                                                        </span>
                                                        <span role="cell" className="sis-branches-table__actions">
                                                            <button
                                                                type="button"
                                                                className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-detail__toggle"
                                                                aria-expanded={expanded}
                                                                aria-controls={panelId}
                                                                title={expanded ? d.hideBranches : d.showBranches}
                                                                aria-label={`${expanded ? d.hideBranches : d.showBranches}: ${school.name}`}
                                                                onClick={(event) => {
                                                                    event.stopPropagation();
                                                                    toggleSchool(school.id);
                                                                }}
                                                            >
                                                                <ChevronDown aria-hidden className={expanded ? 'is-collapsed' : undefined} />
                                                            </button>
                                                        </span>
                                                    </div>
                                                    {expanded ? (
                                                        <div id={panelId} className="sis-org-branches" role="row">
                                                            <div role="cell" className="sis-org-branches__cell">
                                                                {school.branches.length === 0 ? (
                                                                    <p className="sis-branches-empty">{d.noBranches}</p>
                                                                ) : (
                                                                    <div className="sis-branches-table sis-org-branches__table" role="table" aria-label={`${d.branches}: ${school.name}`}>
                                                                        <div className="sis-branches-table__row sis-branches-table__row--head" role="row">
                                                                            <span role="columnheader" className="sis-org-branches__col-branch">{d.branch}</span>
                                                                            <span role="columnheader" className="sis-org-branches__col-departments">{d.departments}</span>
                                                                            <span role="columnheader" className="sis-org-branches__col-count">{d.count}</span>
                                                                        </div>
                                                                        {school.branches.map((branch) => (
                                                                            <div key={branch.id} className="sis-branches-table__row" role="row">
                                                                                <span role="cell" className="sis-org-branches__col-branch">
                                                                                    {branch.name}
                                                                                </span>
                                                                                <span role="cell" className="sis-org-branches__col-departments">
                                                                                    {branch.departments.length === 0
                                                                                        ? <span className="sis-org-branches__none">{d.noDepartments}</span>
                                                                                        : branch.departments.map((department) => department.name).join('، ')}
                                                                                </span>
                                                                                <span role="cell" className="sis-org-branches__col-count" dir="ltr">
                                                                                    {branch.departments.length}
                                                                                </span>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                                <button
                                                                    type="button"
                                                                    className="sis-branches-add-row sis-org-branches__manage"
                                                                    title={d.manageBranchesHint}
                                                                    disabled={!schoolActive}
                                                                    onClick={() => openBranches(school.id)}
                                                                >
                                                                    <GitBranch aria-hidden />
                                                                    {d.manageBranches}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ) : null}
                                                </Fragment>
                                            );
                                        })
                                    )}
                                </div>
                                {canManageSchools ? (
                                    <button
                                        type="button"
                                        className="sis-branches-add-row"
                                        disabled={!selectedActive}
                                        title={selectedActive ? undefined : d.inactiveDirectorateHint}
                                        onClick={() => openAddSchool(selected.id)}
                                    >
                                        <Plus aria-hidden />
                                        {d.addSchoolToDirectorate}
                                    </button>
                                ) : null}
                            </>
                        )}
                    </section>
                </div>
            </div>

            {/* 1) Add directorate */}
            {addDirectorateOpen ? (
                <RegistrySheetDialog title={d.addDirectorate} className="sis-branches-sheet" onClose={() => setAddDirectorateOpen(false)}>
                    <SheetSection id="org-add-directorate" title={d.addDirectorate}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryTextField
                                label={d.directorateName}
                                editing
                                required
                                value={newDirectorate.name}
                                onChange={(name) => setNewDirectorate((current) => ({ ...current, name }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryTextField
                                label={d.region}
                                editing
                                value={newDirectorate.region}
                                onChange={(region) => setNewDirectorate((current) => ({ ...current, region }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryListField
                                label={r.statusLabel}
                                editing
                                required
                                value={newDirectorate.status}
                                display={statusLabel(Number(newDirectorate.status))}
                                options={statusOptions()}
                                onChange={(status) => setNewDirectorate((current) => ({ ...current, status }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setAddDirectorateOpen(false)}>
                            {d.cancel}
                        </Button>
                        <Button type="button" disabled={saving || newDirectorate.name.trim() === ''} onClick={() => void createDirectorate()}>
                            {saving ? i18n.common.saving : d.add}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {/* 2) Add school · 3) Edit school */}
            {schoolDraft !== null ? (
                <RegistrySheetDialog
                    title={schoolDraft.id === null ? d.addSchool : schoolDraft.viewOnly ? r.viewSchool : d.editSchool}
                    className="sis-branches-sheet"
                    onClose={() => setSchoolDraft(null)}
                >
                    <SheetSection id="org-school" title={schoolDraft.id === null ? d.addSchool : schoolDraft.viewOnly ? r.viewSchool : d.editSchool}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryTextField
                                label={d.schoolName}
                                editing={schoolDraft.viewOnly !== true}
                                required
                                value={schoolDraft.name}
                                onChange={(name) => setSchoolDraft((current) => (current === null ? current : { ...current, name }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryListField
                                label={d.directorate}
                                editing={schoolDraft.viewOnly !== true}
                                required
                                value={schoolDraft.directorate_id}
                                display={directorates.find((item) => String(item.id) === schoolDraft.directorate_id)?.name ?? '—'}
                                options={directorateOptions(editedSchool?.directorate.id ?? null)}
                                onChange={(directorate_id) =>
                                    setSchoolDraft((current) => (current === null ? current : { ...current, directorate_id }))
                                }
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryTextField
                                label={d.phone}
                                editing={schoolDraft.viewOnly !== true}
                                type="tel"
                                dir="ltr"
                                value={schoolDraft.phone}
                                onChange={(phone) => setSchoolDraft((current) => (current === null ? current : { ...current, phone }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryTextField
                                label={d.email}
                                editing={schoolDraft.viewOnly !== true}
                                type="email"
                                dir="ltr"
                                value={schoolDraft.email}
                                onChange={(email) => setSchoolDraft((current) => (current === null ? current : { ...current, email }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryTextField
                                label={d.address}
                                editing={schoolDraft.viewOnly !== true}
                                value={schoolDraft.address}
                                onChange={(address) => setSchoolDraft((current) => (current === null ? current : { ...current, address }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryListField
                                label={r.statusLabel}
                                editing={schoolDraft.viewOnly !== true}
                                required
                                value={schoolDraft.status}
                                display={statusLabel(Number(schoolDraft.status))}
                                options={statusOptions()}
                                onChange={(status) => setSchoolDraft((current) => (current === null ? current : { ...current, status }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setSchoolDraft(null)}>
                            {d.cancel}
                        </Button>
                        {schoolDraft.viewOnly ? (
                            canManageSchools ? (
                                <Button
                                    type="button"
                                    onClick={() => setSchoolDraft((current) => (current === null ? current : { ...current, viewOnly: false }))}
                                >
                                    {i18n.common.edit}
                                </Button>
                            ) : null
                        ) : (
                            <Button
                                type="button"
                                disabled={saving || schoolDraft.name.trim() === '' || schoolDraft.directorate_id === ''}
                                onClick={saveSchool}
                            >
                                {saving ? i18n.common.saving : schoolDraft.id === null ? d.add : d.saveChanges}
                            </Button>
                        )}
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {/* 4) A directorate's schools: pick a directorate → tick schools → move them into it. */}
            {manageOpen ? (
                <RegistrySheetDialog title={d.manageTitle} className="sis-branches-sheet sis-branches-manage" onClose={() => setManageOpen(false)}>
                    <SheetSection id="org-manage-directorate" title={d.directorate}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryListField
                                label={d.chooseDirectorate}
                                editing
                                required
                                value={manageDirectorateId}
                                display={manageDirectorate?.name ?? '—'}
                                options={directorateListOptions}
                                onChange={(next) => {
                                    setManageDirectorateId(next);
                                    setManageChecked([]);
                                    setManageSearch('');
                                }}
                                fieldClassName="sis-branches-field--wide"
                            />
                        </div>
                    </SheetSection>

                    <SheetSection id="org-manage-schools" title={`${d.directorateSchools} (${manageInIds.length})`}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <label className="sis-admission-sheet__field sis-branches-field--wide">
                                <span className="sis-admission-sheet__label">{d.searchInSchools}</span>
                                <span className="sis-branches-search">
                                    <Search aria-hidden className="sis-branches-search__icon" />
                                    <input
                                        className="sis-branches-search__input"
                                        value={manageSearch}
                                        placeholder={d.searchSchools}
                                        dir="rtl"
                                        onChange={(event) => setManageSearch(event.target.value)}
                                    />
                                </span>
                            </label>

                            <div className="sis-branches-checklist-bar sis-branches-field--wide">
                                <label className="sis-branches-checklist__item sis-branches-checklist__item--all">
                                    <Checkbox
                                        checked={
                                            manageMovable.length > 0 &&
                                            manageMovable.every((school) => manageChecked.includes(school.id))
                                        }
                                        disabled={!manageCanMove || manageMovable.length === 0}
                                        onCheckedChange={(checked) =>
                                            setManageChecked(checked === true ? manageMovable.map((school) => school.id) : [])
                                        }
                                    />
                                    <span>{d.selectAll}</span>
                                </label>
                                <span className="sis-branches-checklist-bar__count" aria-live="polite">
                                    {d.selectedCount}: {manageChecked.length} {d.of} {allSchools.length - manageInIds.length}
                                </span>
                            </div>

                            <ul className="sis-branches-checklist sis-branches-field--wide">
                                {manageSchools.length === 0 ? (
                                    <li className="sis-branches-empty">
                                        {manageSearch.trim() !== '' ? d.noSearchSchools : d.noUserSchools}
                                    </li>
                                ) : (
                                    manageSchools.map((school) => {
                                        const inside = manageInIds.includes(school.id);
                                        const checked = inside || manageChecked.includes(school.id);

                                        return (
                                            <li key={school.id}>
                                                <label
                                                    className={`sis-branches-checklist__item${
                                                        manageChecked.includes(school.id) ? ' is-checked sis-org-checklist--move' : ''
                                                    }${inside ? ' sis-org-checklist--inside' : ''}`}
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        disabled={inside || !manageCanMove}
                                                        onCheckedChange={(next) =>
                                                            setManageChecked((current) =>
                                                                next === true
                                                                    ? [...current, school.id]
                                                                    : current.filter((id) => id !== school.id),
                                                            )
                                                        }
                                                    />
                                                    <span className="sis-branches-checklist__text">
                                                        <span className="sis-branches-checklist__name">{school.name}</span>
                                                        <span className="sis-branches-checklist__desc">
                                                            {inside ? d.alreadyInDirectorate : `${d.currentDirectorate}: ${school.directorate.name}`}
                                                        </span>
                                                    </span>
                                                </label>
                                            </li>
                                        );
                                    })
                                )}
                            </ul>
                            <p className="sis-admission-sheet__empty sis-branches-field--wide" role="note">
                                {manageCanMove ? d.moveHint : d.inactiveDirectorateHint}
                            </p>
                        </div>
                    </SheetSection>

                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setManageOpen(false)}>
                            {d.close}
                        </Button>
                        {canManageSchools ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={saving || !manageCanMove}
                                onClick={() => openAddSchool(manageDirectorate?.id ?? null)}
                            >
                                <Plus aria-hidden />
                                {d.addSchoolToDirectorate}
                            </Button>
                        ) : null}
                        <Button type="button" disabled={saving || !manageCanMove || manageChecked.length === 0} onClick={() => void moveSchools()}>
                            <ArrowLeftRight aria-hidden />
                            {d.moveSelected} ({manageChecked.length})
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            <ConfirmDialog
                open={deleteDirectorate !== null}
                title={d.deleteDirectorateTitle}
                description={d.deleteDirectorateConfirm}
                confirmLabel={d.deleteDirectorate}
                tone="danger"
                confirmPending={saving}
                onConfirm={() =>
                    deleteDirectorate && void setStatus('directorates', deleteDirectorate.id, 3, () => setDeleteDirectorate(null))
                }
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setDeleteDirectorate(null);
                    }
                }}
            />
            <ConfirmDialog
                open={deleteSchool !== null}
                title={d.deleteSchoolTitle}
                description={d.deleteSchoolConfirm}
                confirmLabel={d.deleteSchool}
                tone="danger"
                confirmPending={saving}
                onConfirm={() => deleteSchool && void setStatus('schools', deleteSchool.id, 3, () => setDeleteSchool(null))}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setDeleteSchool(null);
                    }
                }}
            />
        </>
    );
}
