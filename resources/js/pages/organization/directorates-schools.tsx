import { Head, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BookOpen,
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
    name: string;
    directorate_id: string;
    phone: string;
    email: string;
    address: string;
};

const ACTIVE = 1;

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

function StatusBadge({ active }: { active: boolean }) {
    const d = t().directorateSchools;

    return <span className={`sis-branches-status${active ? '' : ' sis-org-status--inactive'}`}>{active ? d.active : d.inactive}</span>;
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
    const [newDirectorate, setNewDirectorate] = useState({ name: '', region: '' });
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
    }, [selected?.id, selected?.name, selected?.region]);

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
        (directorateName.trim() !== selected.name || blankToNull(directorateRegion) !== (selected.region ?? null));

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
        run(() =>
            request('patch', `/organization/directorates/${selected.id}`, {
                name: directorateName.trim(),
                region: blankToNull(directorateRegion),
            }),
        );

    const createDirectorate = () =>
        run(
            async () => {
                knownDirectoratesRef.current = new Set(directorates.map((item) => item.id));
                const created = await request('post', '/organization/directorates', {
                    name: newDirectorate.name.trim(),
                    region: blankToNull(newDirectorate.region),
                });
                if (!created) {
                    knownDirectoratesRef.current = null;
                }

                return created;
            },
            () => {
                setAddDirectorateOpen(false);
                setNewDirectorate({ name: '', region: '' });
            },
        );

    const openAddSchool = (directorateId: number | null) => {
        const target = directorates.find((item) => item.id === directorateId && item.status === ACTIVE) ?? activeDirectorates[0];
        setSchoolDraft({
            id: null,
            name: '',
            directorate_id: target ? String(target.id) : '',
            phone: '',
            email: '',
            address: '',
        });
    };

    function openEditSchool(school: School, directorateId: number) {
        setSchoolDraft({
            id: school.id,
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
        if (schoolDraft.id === null) {
            void run(() => request('post', '/organization/schools', payload), () => setSchoolDraft(null));

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
        if (Object.keys(changed).length === 0) {
            setSchoolDraft(null);

            return;
        }
        void run(() => request('patch', `/organization/schools/${editedSchool.id}`, changed), () => setSchoolDraft(null));
    };

    const setStatus = (kind: 'schools' | 'directorates', id: number, active: boolean, after?: () => void) =>
        run(() => request('post', `/organization/${kind}/${id}/${active ? 'reactivate' : 'deactivate'}`), after);

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

    return (
        <>
            <Head title={d.title} />
            <div className="sis-ops-hub sis-branches-page sis-org-page" dir="rtl" lang="ar">
                <header className="sis-branches-page__head">
                    <div className="sis-branches-page__heading">
                        <h1 className="sis-branches-page__title">{d.heading}</h1>
                    </div>
                    {canManageDirectorates || canManageSchools ? (
                        <div className="sis-branches-page__actions">
                            {canManageDirectorates ? (
                                <Button type="button" className="sis-org-head-btn" onClick={() => setAddDirectorateOpen(true)}>
                                    <PlusCircle aria-hidden />
                                    {d.addDirectorate}
                                </Button>
                            ) : null}
                            {canManageSchools ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="sis-org-head-btn"
                                    onClick={() => openAddSchool(selectedId)}
                                    disabled={activeDirectorates.length === 0}
                                    title={activeDirectorates.length === 0 ? d.noActiveDirectorates : undefined}
                                >
                                    <Building2 aria-hidden />
                                    {d.addSchool}
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
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
                                        {directorates.length}
                                    </span>
                                </span>
                            </div>
                            <ul className="sis-branches-list__items">
                                {directorates.length === 0 ? (
                                    <li className="sis-branches-empty">{d.noDirectorates}</li>
                                ) : (
                                    directorates.map((item) => {
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
                                                                title={d.editDirectorate}
                                                                aria-label={`${d.editDirectorate}: ${item.name}`}
                                                                onClick={(event) => {
                                                                    event.stopPropagation();
                                                                    setSelectedId(item.id);
                                                                    setCollapsed(false);
                                                                }}
                                                            >
                                                                <Pencil aria-hidden />
                                                            </button>
                                                            {active ? (
                                                                <button
                                                                    type="button"
                                                                    className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-icon-btn--danger"
                                                                    title={d.deleteDirectorate}
                                                                    aria-label={`${d.deleteDirectorate}: ${item.name}`}
                                                                    onClick={(event) => {
                                                                        event.stopPropagation();
                                                                        setDeleteDirectorate(item);
                                                                    }}
                                                                >
                                                                    <Trash2 aria-hidden />
                                                                </button>
                                                            ) : (
                                                                <button
                                                                    type="button"
                                                                    className="sis-branches-icon-btn sis-branches-icon-btn--plain"
                                                                    title={d.reactivateDirectorate}
                                                                    aria-label={`${d.reactivateDirectorate}: ${item.name}`}
                                                                    disabled={saving}
                                                                    onClick={(event) => {
                                                                        event.stopPropagation();
                                                                        void setStatus('directorates', item.id, true);
                                                                    }}
                                                                >
                                                                    <RotateCcw aria-hidden />
                                                                </button>
                                                            )}
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
                                    <StatusBadge active={selectedActive} />
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
                                                        className={`sis-branches-table__row${expanded ? ' is-expanded' : ''}${schoolActive ? '' : ' sis-org-item--inactive'}`}
                                                        role="row"
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
                                                                onClick={() => toggleSchool(school.id)}
                                                            >
                                                                <ChevronDown aria-hidden className={expanded ? 'is-collapsed' : undefined} />
                                                            </button>
                                                            {canManageSchools ? (
                                                                <>
                                                                    <button
                                                                        type="button"
                                                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain"
                                                                        title={d.editSchool}
                                                                        aria-label={`${d.editSchool}: ${school.name}`}
                                                                        onClick={() => openEditSchool(school, selected.id)}
                                                                    >
                                                                        <Pencil aria-hidden />
                                                                    </button>
                                                                    {schoolActive ? (
                                                                        <button
                                                                            type="button"
                                                                            className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-icon-btn--danger"
                                                                            title={d.deleteSchool}
                                                                            aria-label={`${d.deleteSchool}: ${school.name}`}
                                                                            onClick={() => setDeleteSchool(school)}
                                                                        >
                                                                            <Trash2 aria-hidden />
                                                                        </button>
                                                                    ) : (
                                                                        <button
                                                                            type="button"
                                                                            className="sis-branches-icon-btn sis-branches-icon-btn--plain"
                                                                            title={d.reactivateSchool}
                                                                            aria-label={`${d.reactivateSchool}: ${school.name}`}
                                                                            disabled={saving}
                                                                            onClick={() => void setStatus('schools', school.id, true)}
                                                                        >
                                                                            <RotateCcw aria-hidden />
                                                                        </button>
                                                                    )}
                                                                </>
                                                            ) : null}
                                                        </span>
                                                    </div>
                                                    {expanded ? (
                                                        <div id={panelId} className="sis-org-branches" role="row">
                                                            <div role="cell" className="sis-org-branches__cell">
                                                                {school.branches.length === 0 ? (
                                                                    <p className="sis-branches-empty">{d.noBranches}</p>
                                                                ) : (
                                                                    <ul className="sis-org-branches__list">
                                                                        {school.branches.map((branch) => (
                                                                            <li key={branch.id} className="sis-org-branches__item">
                                                                                <span className="sis-org-branches__branch">
                                                                                    <GitBranch aria-hidden />
                                                                                    {branch.name}
                                                                                </span>
                                                                                <span className="sis-org-branches__departments">
                                                                                    {branch.departments.length === 0 ? (
                                                                                        <span className="sis-org-branches__none">{d.noDepartments}</span>
                                                                                    ) : (
                                                                                        branch.departments.map((department) => (
                                                                                            <span key={department.id} className="sis-org-chip">
                                                                                                <BookOpen aria-hidden />
                                                                                                {department.name}
                                                                                            </span>
                                                                                        ))
                                                                                    )}
                                                                                </span>
                                                                            </li>
                                                                        ))}
                                                                    </ul>
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
                    title={schoolDraft.id === null ? d.addSchool : d.editSchool}
                    className="sis-branches-sheet"
                    onClose={() => setSchoolDraft(null)}
                >
                    <SheetSection id="org-school" title={schoolDraft.id === null ? d.addSchool : d.editSchool}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryTextField
                                label={d.schoolName}
                                editing
                                required
                                value={schoolDraft.name}
                                onChange={(name) => setSchoolDraft((current) => (current === null ? current : { ...current, name }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryListField
                                label={d.directorate}
                                editing
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
                                editing
                                type="tel"
                                dir="ltr"
                                value={schoolDraft.phone}
                                onChange={(phone) => setSchoolDraft((current) => (current === null ? current : { ...current, phone }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryTextField
                                label={d.email}
                                editing
                                type="email"
                                dir="ltr"
                                value={schoolDraft.email}
                                onChange={(email) => setSchoolDraft((current) => (current === null ? current : { ...current, email }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <RegistryTextField
                                label={d.address}
                                editing
                                value={schoolDraft.address}
                                onChange={(address) => setSchoolDraft((current) => (current === null ? current : { ...current, address }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setSchoolDraft(null)}>
                            {d.cancel}
                        </Button>
                        <Button
                            type="button"
                            disabled={saving || schoolDraft.name.trim() === '' || schoolDraft.directorate_id === ''}
                            onClick={saveSchool}
                        >
                            {saving ? i18n.common.saving : schoolDraft.id === null ? d.add : d.saveChanges}
                        </Button>
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
                    deleteDirectorate && void setStatus('directorates', deleteDirectorate.id, false, () => setDeleteDirectorate(null))
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
                onConfirm={() => deleteSchool && void setStatus('schools', deleteSchool.id, false, () => setDeleteSchool(null))}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setDeleteSchool(null);
                    }
                }}
            />
        </>
    );
}
