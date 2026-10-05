import { Head, Link, usePage } from '@inertiajs/react';
import {
    Atom,
    BookMarked,
    BookOpen,
    CheckCircle2,
    CircleSlash,
    Eye,
    FolderTree,
    Layers,
    Save,
    XCircle,
    Calculator,
    ChevronUp,
    ConciergeBell,
    Cpu,
    DraftingCompass,
    Feather,
    GraduationCap,
    HeartPulse,
    MoreVertical,
    Palette,
    Pencil,
    Plus,
    PlusCircle,
    Scissors,
    Search,
    Sprout,
    Trash2,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { useRegisterPageRibbon, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
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

type Department = { id: number; code: string; name: string; description: string | null };
type Branch = { id: number; code: string; name: string; description: string | null; departments: Department[] };

type Props = {
    branches: Branch[];
    authorization: { can_manage: boolean };
};

type DepartmentDraft = { id: number | null; name: string; description: string; branch_id: string };

const RELOAD_PROPS = ['branches', 'flash'];

/**
 * Branch tile icon + tone by the branch's academic field (name). First match wins, so
 * more specific fields come first (e.g. «المحاسبة الفندقية» is hospitality, not commercial).
 */
const BRANCH_VISUALS: { keywords: string[]; icon: LucideIcon; tone: string }[] = [
    { keywords: ['فندق', 'سياح'], icon: ConciergeBell, tone: 'hospitality' },
    { keywords: ['حاسوب', 'معلومات', 'برمج'], icon: Cpu, tone: 'computing' },
    { keywords: ['صناع'], icon: DraftingCompass, tone: 'industrial' },
    { keywords: ['زراع'], icon: Sprout, tone: 'agricultural' },
    { keywords: ['تجار', 'محاسب'], icon: Calculator, tone: 'commercial' },
    { keywords: ['تمريض', 'صح'], icon: HeartPulse, tone: 'health' },
    { keywords: ['نسوي', 'منزلي'], icon: Scissors, tone: 'home' },
    { keywords: ['فنون', 'تشكيل'], icon: Palette, tone: 'arts' },
    { keywords: ['شرع', 'ديني'], icon: BookMarked, tone: 'religious' },
    { keywords: ['أدب', 'ادب'], icon: Feather, tone: 'literary' },
    { keywords: ['علمي', 'علوم'], icon: Atom, tone: 'scientific' },
];

function branchVisual(name: string): { icon: LucideIcon; tone: string } {
    const n = name.replace(/\s+/g, '');
    const match = BRANCH_VISUALS.find((visual) => visual.keywords.some((keyword) => n.includes(keyword)));

    return match ?? { icon: GraduationCap, tone: 'default' };
}

function BranchTile({ name, large = false }: { name: string; large?: boolean }) {
    const { icon: Icon, tone } = branchVisual(name);

    return (
        <span className={`sis-branches-tile sis-branches-tile--${tone}${large ? ' sis-branches-tile--lg' : ''}`} aria-hidden="true">
            <Icon />
        </span>
    );
}

function DescriptionField({
    label,
    value,
    placeholder,
    onChange,
    disabled = false,
}: {
    label: string;
    value: string;
    placeholder: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}) {
    return (
        <label className="sis-admission-sheet__field sis-branches-field--wide">
            <span className="sis-admission-sheet__label">{label}</span>
            <textarea
                className="sis-admission-sheet__control sis-branches-textarea"
                value={value}
                placeholder={placeholder}
                rows={3}
                dir="rtl"
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
            />
        </label>
    );
}

export default function OrganizationBranches(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().branchStructure.title, href: '/organization/branches' }];

    // Inner component: page error / flash contexts live inside AppLayout.
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <BranchesPage {...props} />
        </AppLayout>
    );
}

function BranchesPage({ branches, authorization }: Props) {
    const i18n = t();
    const b = i18n.branchStructure;
    const canManage = authorization.can_manage;
    const request = useRegistryRequest(RELOAD_PROPS);
    const schoolContext = usePage().props.schoolContext as
        | { schoolId: number | null; schools: Array<{ id: number; name: string }> }
        | undefined;
    const schoolName = schoolContext?.schools.find((school) => school.id === schoolContext.schoolId)?.name ?? null;

    const [selectedId, setSelectedId] = useState<number | null>(branches[0]?.id ?? null);
    const [collapsed, setCollapsed] = useState(true);
    const [branchName, setBranchName] = useState('');
    const [branchDescription, setBranchDescription] = useState('');
    const [saving, setSaving] = useState(false);

    const [addBranchOpen, setAddBranchOpen] = useState(false);
    const [newBranch, setNewBranch] = useState({ name: '', description: '' });
    const [departmentDraft, setDepartmentDraft] = useState<DepartmentDraft | null>(null);
    const [manageOpen, setManageOpen] = useState(false);
    const [manageBranchId, setManageBranchId] = useState('');
    const [manageSearch, setManageSearch] = useState('');
    const [manageChecked, setManageChecked] = useState<number[]>([]);
    const [confirmBulkDelete, setConfirmBulkDelete] = useState(false);
    const [deleteBranch, setDeleteBranch] = useState<Branch | null>(null);
    const [deleteDepartment, setDeleteDepartment] = useState<Department | null>(null);

    const selected = branches.find((branch) => branch.id === selectedId) ?? null;

    // Keep a valid selection after reloads (create / delete).
    useEffect(() => {
        if (selectedId === null || !branches.some((branch) => branch.id === selectedId)) {
            setSelectedId(branches[0]?.id ?? null);
        }
    }, [branches, selectedId]);

    useEffect(() => {
        setBranchName(selected?.name ?? '');
        setBranchDescription(selected?.description ?? '');
    }, [selected?.id, selected?.name, selected?.description]);

    // «تحرير» ribbon filter tabs + titlebar search (same mechanism as the students page).
    const [filterKey, setFilterKey] = useState<'all' | 'with' | 'without'>('all');
    const [query, setQuery] = useState('');
    const nameInputRef = useRef<HTMLInputElement | null>(null);
    const withCount = branches.filter((branch) => branch.departments.length > 0).length;
    const visibleBranches = useMemo(() => {
        const q = query.trim();

        return branches.filter((branch) => {
            if (filterKey === 'with' && branch.departments.length === 0) return false;
            if (filterKey === 'without' && branch.departments.length > 0) return false;

            return q === '' || branch.name.includes(q) || branch.departments.some((department) => department.name.includes(q));
        });
    }, [branches, filterKey, query]);

    const branchOptions = branches.map((branch) => ({ value: String(branch.id), label: branch.name }));
    const branchDirty =
        selected !== null &&
        (branchName.trim() !== selected.name || (branchDescription.trim() || null) !== (selected.description ?? null));

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

    const saveBranch = () =>
        selected &&
        run(() =>
            request('patch', `/organization/branches/${selected.id}`, {
                name: branchName.trim(),
                description: blankToNull(branchDescription),
            }),
        );

    const createBranch = () =>
        run(
            () =>
                request('post', '/organization/branches', {
                    name: newBranch.name.trim(),
                    description: blankToNull(newBranch.description),
                }),
            () => {
                setAddBranchOpen(false);
                setNewBranch({ name: '', description: '' });
            },
        );

    const saveDepartment = () => {
        if (departmentDraft === null) {
            return;
        }
        const payload = {
            name: departmentDraft.name.trim(),
            description: blankToNull(departmentDraft.description),
            branch_id: Number(departmentDraft.branch_id),
        };
        void run(
            () =>
                departmentDraft.id === null
                    ? request('post', '/organization/departments', payload)
                    : request('patch', `/organization/departments/${departmentDraft.id}`, payload),
            () => setDepartmentDraft(null),
        );
    };

    const deleteDepartments = (ids: number[], after?: () => void) =>
        run(() => request('post', '/organization/departments/delete', { department_ids: ids }), after);

    const openAddDepartment = (branchId: number | null) =>
        setDepartmentDraft({ id: null, name: '', description: '', branch_id: branchId === null ? '' : String(branchId) });

    const openEditDepartment = (department: Department, branchId: number) =>
        setDepartmentDraft({
            id: department.id,
            name: department.name,
            description: department.description ?? '',
            branch_id: String(branchId),
        });

    const openManage = (branchId: number) => {
        setManageBranchId(String(branchId));
        setManageSearch('');
        setManageChecked([]);
        setManageOpen(true);
    };

    const manageBranch = branches.find((branch) => String(branch.id) === manageBranchId) ?? null;
    const manageDepartments = (manageBranch?.departments ?? []).filter(
        (department) => manageSearch.trim() === '' || department.name.includes(manageSearch.trim()),
    );

    const r = i18n.orgRibbon;
    const branchesPercent = branches.length === 0 ? 0 : Math.round((withCount / branches.length) * 100);
    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const noSelection = selected === null;
        const groups: PageRibbonGroup[] = [
            {
                id: 'org-branch-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'org-branch-view',
                        label: i18n.common.view,
                        icon: Eye,
                        title: noSelection ? r.needsSelection : i18n.common.view,
                        disabled: noSelection,
                        onSelect: () => setCollapsed(false),
                    },
                    ...(canManage
                        ? [
                              {
                                  id: 'org-branch-edit',
                                  label: i18n.common.edit,
                                  icon: Pencil,
                                  tone: 'edit' as const,
                                  disabled: noSelection || saving,
                                  onSelect: () => {
                                      setCollapsed(false);
                                      window.requestAnimationFrame(() => nameInputRef.current?.focus());
                                  },
                              },
                              {
                                  id: 'org-branch-save',
                                  label: i18n.common.save,
                                  icon: Save,
                                  tone: 'save' as const,
                                  disabled: !branchDirty || saving || branchName.trim() === '',
                                  onSelect: () => void saveBranch(),
                              },
                          ]
                        : []),
                    {
                        id: 'org-branch-cancel',
                        label: i18n.common.cancel,
                        icon: XCircle,
                        disabled: noSelection || saving,
                        onSelect: () => {
                            setBranchName(selected?.name ?? '');
                            setBranchDescription(selected?.description ?? '');
                            setCollapsed(true);
                        },
                    },
                    ...(canManage
                        ? [
                              {
                                  id: 'org-branch-delete',
                                  label: i18n.common.delete,
                                  icon: Trash2,
                                  tone: 'delete' as const,
                                  disabled: noSelection || saving,
                                  onSelect: () => selected && setDeleteBranch(selected),
                              },
                          ]
                        : []),
                ],
            },
            {
                id: 'org-branch-filters',
                label: r.departments,
                commands: [
                    { key: 'all' as const, label: r.all, icon: Layers, count: branches.length },
                    { key: 'with' as const, label: r.withDepartments, icon: CheckCircle2, count: withCount },
                    { key: 'without' as const, label: r.withoutDepartments, icon: CircleSlash, count: branches.length - withCount },
                ].map((tab) => ({
                    id: `org-branch-filter-${tab.key}`,
                    label: tab.label,
                    title: `${tab.label} (${tab.count})`,
                    icon: tab.icon,
                    count: tab.count,
                    pressed: filterKey === tab.key,
                    onSelect: () => setFilterKey(filterKey === tab.key && tab.key !== 'all' ? 'all' : tab.key),
                })),
            },
        ];
        if (canManage) {
            groups.push({
                id: 'org-branch-manage',
                label: r.manage,
                commands: [
                    { id: 'org-branch-add', label: b.addBranch, icon: PlusCircle, onSelect: () => setAddBranchOpen(true) },
                    {
                        id: 'org-department-add',
                        label: b.addDepartment,
                        icon: BookOpen,
                        disabled: branches.length === 0,
                        onSelect: () => openAddDepartment(selectedId),
                    },
                    {
                        id: 'org-departments-manage',
                        label: b.manageTitle,
                        icon: FolderTree,
                        disabled: noSelection,
                        onSelect: () => selected && openManage(selected.id),
                    },
                ],
            });
        }
        groups.push({
            id: 'org-branch-progress',
            label: r.progress,
            commands: [],
            custom: (
                <div
                    className="sis-ribbon__progress-track"
                    role="progressbar"
                    aria-label={r.branchesProgress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={branchesPercent}
                    data-contrast={branchesPercent >= 45 ? 'light' : 'dark'}
                    dir="rtl"
                    title={`${r.branchesProgress}: ${branchesPercent}%`}
                >
                    <span className="sis-ribbon__progress-fill" style={{ width: `${branchesPercent}%` }} />
                    <span className="sis-ribbon__progress-value" dir="ltr">
                        {branchesPercent}%
                    </span>
                </div>
            ),
        });

        return groups;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [branchDirty, branchName, branches, branchesPercent, canManage, filterKey, i18n, saving, selected, selectedId, withCount]);
    useRegisterPageRibbon('edit', editRibbonGroups);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: query,
            label: r.searchBranches,
            placeholder: r.searchBranches,
            onDraftChange: setQuery,
            onCommit: setQuery,
        }),
        [query, r.searchBranches],
    );
    useRegisterPageTitlebarSearch(titlebarSearch);

    return (
        <>
            <Head title={b.title} />
            <div className="sis-ops-hub sis-branches-page" dir="rtl" lang="ar">
                <header className="sis-branches-page__head">
                    <div className="sis-branches-page__heading">
                        <h1 className="sis-branches-page__title">{b.heading}</h1>
                        {schoolName !== null ? (
                            <p className="sis-branches-page__subtitle">
                                {b.school}:{' '}
                                <Link href={`/organization/directorates-schools?school=${schoolContext?.schoolId ?? ''}`}>{schoolName}</Link>
                            </p>
                        ) : null}
                    </div>
                    {canManage ? (
                        <div className="sis-branches-page__actions">
                            <Button type="button" variant="outline" className="sis-branches-soft-btn" onClick={() => setAddBranchOpen(true)}>
                                <PlusCircle aria-hidden />
                                {b.addBranch}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="sis-branches-soft-btn"
                                onClick={() => openAddDepartment(selectedId)}
                                disabled={branches.length === 0}
                            >
                                <BookOpen aria-hidden />
                                {b.addDepartment}
                            </Button>
                        </div>
                    ) : null}
                </header>
                {canManage ? null : <p className="sis-branches-page__notice">{b.readOnly}</p>}

                <div className="sis-branches-page__grid">
                    {/* Branches list */}
                    <section className="sis-branches-card sis-branches-list" aria-label={b.branchesTitle}>
                        <div className="sis-branches-card__head">
                            <h2 className="sis-branches-card__title">{b.branchesTitle}</h2>
                            <span className="sis-branches-count" dir="ltr">
                                {branches.length}
                            </span>
                        </div>
                        <div className="sis-branches-table sis-branches-table--branches" role="table" aria-label={b.branchesTitle}>
                            <div className="sis-branches-table__row sis-branches-table__row--head" role="row">
                                <span role="columnheader">{b.branch}</span>
                                <span role="columnheader" className="sis-branches-table__actions" />
                            </div>
                            {visibleBranches.length === 0 ? (
                                <div className="sis-branches-table__row" role="row">
                                    <span role="cell" className="sis-branches-empty">
                                        {branches.length === 0 ? b.noBranches : b.noSearchResult}
                                    </span>
                                </div>
                            ) : (
                                visibleBranches.map((branch) => (
                                    <div
                                        key={branch.id}
                                        className={`sis-branches-table__row sis-branches-table__row--branch${
                                            branch.id === selectedId ? ' is-selected' : ''
                                        }`}
                                        role="row"
                                        tabIndex={0}
                                        aria-selected={branch.id === selectedId}
                                        onClick={() => setSelectedId(branch.id)}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                setSelectedId(branch.id);
                                            }
                                        }}
                                    >
                                        <span role="cell" className="sis-branches-table__name">
                                            <BranchTile name={branch.name} />
                                            <span className="sis-branches-item__text">
                                                <span className="sis-branches-item__name">{branch.name}</span>
                                                <span className="sis-branches-item__meta">
                                                    {branch.departments.length} {b.departmentsCount}
                                                </span>
                                            </span>
                                        </span>
                                        <span role="cell" className="sis-branches-table__actions">
                                            {canManage ? (
                                                <>
                                                    <button
                                                        type="button"
                                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain"
                                                        title={b.editBranch}
                                                        aria-label={`${b.editBranch}: ${branch.name}`}
                                                        onClick={(event) => {
                                                            event.stopPropagation();
                                                            setSelectedId(branch.id);
                                                            setCollapsed(false);
                                                        }}
                                                    >
                                                        <Pencil aria-hidden />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-icon-btn--danger"
                                                        title={b.deleteBranch}
                                                        aria-label={`${b.deleteBranch}: ${branch.name}`}
                                                        onClick={(event) => {
                                                            event.stopPropagation();
                                                            setDeleteBranch(branch);
                                                        }}
                                                    >
                                                        <Trash2 aria-hidden />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain"
                                                        title={b.manageTitle}
                                                        aria-label={`${b.more}: ${branch.name}`}
                                                        onClick={(event) => {
                                                            event.stopPropagation();
                                                            openManage(branch.id);
                                                        }}
                                                    >
                                                        <MoreVertical aria-hidden />
                                                    </button>
                                                </>
                                            ) : null}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                        {canManage ? (
                            <button type="button" className="sis-branches-add-row" onClick={() => setAddBranchOpen(true)}>
                                <Plus aria-hidden />
                                {b.addBranch}
                            </button>
                        ) : null}
                    </section>

                    {/* Selected branch */}
                    <section className="sis-branches-card sis-branches-detail" aria-label={selected?.name ?? b.branchesTitle}>
                        {selected === null ? (
                            <p className="sis-branches-empty">{b.selectBranch}</p>
                        ) : (
                            <>
                                <div className="sis-branches-card__head sis-branches-detail__head">
                                    <BranchTile name={selected.name} large />
                                    <h2 className="sis-branches-card__title">
                                        {b.branchPrefix} {selected.name}
                                    </h2>
                                    <span className="sis-branches-status">{b.active}</span>
                                    <button
                                        type="button"
                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-detail__toggle"
                                        aria-expanded={!collapsed}
                                        title={collapsed ? b.expand : b.collapse}
                                        aria-label={collapsed ? b.expand : b.collapse}
                                        onClick={() => setCollapsed((value) => !value)}
                                    >
                                        <ChevronUp aria-hidden className={collapsed ? 'is-collapsed' : undefined} />
                                    </button>
                                </div>

                                {collapsed ? null : (
                                    <div className="sis-branches-detail__form">
                                        <RegistryTextField
                                            label={b.branchName}
                                            inputRef={nameInputRef}
                                            editing={canManage}
                                            required
                                            value={branchName}
                                            onChange={setBranchName}
                                            fieldClassName="sis-branches-field--wide"
                                        />
                                        <DescriptionField
                                            label={b.description}
                                            value={branchDescription}
                                            placeholder={b.branchDescriptionPlaceholder}
                                            onChange={setBranchDescription}
                                            disabled={!canManage}
                                        />
                                        {canManage ? (
                                            <div className="sis-branches-detail__buttons">
                                                <Button type="button" disabled={!branchDirty || saving || branchName.trim() === ''} onClick={() => void saveBranch()}>
                                                    {saving ? i18n.common.saving : b.saveChanges}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={!branchDirty || saving}
                                                    onClick={() => {
                                                        setBranchName(selected.name);
                                                        setBranchDescription(selected.description ?? '');
                                                    }}
                                                >
                                                    {b.cancel}
                                                </Button>
                                            </div>
                                        ) : null}
                                    </div>
                                )}

                                <div className="sis-branches-table" role="table" aria-label={b.branchDepartments}>
                                    <div className="sis-branches-table__row sis-branches-table__row--head" role="row">
                                        <span role="columnheader" className="sis-branches-table__heading">
                                            {b.department}
                                            <span className="sis-branches-count" dir="ltr">
                                                {selected.departments.length}
                                            </span>
                                        </span>
                                        <span role="columnheader" className="sis-branches-table__actions" />
                                    </div>
                                    {selected.departments.length === 0 ? (
                                        <div className="sis-branches-table__row" role="row">
                                            <span role="cell" className="sis-branches-empty">
                                                {b.noDepartments}
                                            </span>
                                        </div>
                                    ) : (
                                        selected.departments.map((department) => (
                                            <div key={department.id} className="sis-branches-table__row" role="row">
                                                <span role="cell" className="sis-branches-table__name">
                                                    <BookOpen aria-hidden className="sis-branches-table__icon" />
                                                    {department.name}
                                                </span>
                                                <span role="cell" className="sis-branches-table__actions">
                                                    {canManage ? (
                                                        <>
                                                            <button
                                                                type="button"
                                                                className="sis-branches-icon-btn sis-branches-icon-btn--plain"
                                                                title={b.editDepartment}
                                                                aria-label={`${b.editDepartment}: ${department.name}`}
                                                                onClick={() => openEditDepartment(department, selected.id)}
                                                            >
                                                                <Pencil aria-hidden />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="sis-branches-icon-btn sis-branches-icon-btn--plain sis-branches-icon-btn--danger"
                                                                title={b.deleteDepartment}
                                                                aria-label={`${b.deleteDepartment}: ${department.name}`}
                                                                onClick={() => setDeleteDepartment(department)}
                                                            >
                                                                <Trash2 aria-hidden />
                                                            </button>
                                                        </>
                                                    ) : null}
                                                </span>
                                            </div>
                                        ))
                                    )}
                                </div>
                                {canManage ? (
                                    <button type="button" className="sis-branches-add-row" onClick={() => openAddDepartment(selected.id)}>
                                        <Plus aria-hidden />
                                        {b.addDepartmentToBranch}
                                    </button>
                                ) : null}
                            </>
                        )}
                    </section>
                </div>
            </div>

            {/* 1) Add branch */}
            {addBranchOpen ? (
                <RegistrySheetDialog title={b.addBranch} className="sis-branches-sheet" onClose={() => setAddBranchOpen(false)}>
                    <SheetSection id="branches-add-branch" title={b.addBranch}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryTextField
                                label={b.branchName}
                                editing
                                required
                                value={newBranch.name}
                                onChange={(name) => setNewBranch((current) => ({ ...current, name }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <DescriptionField
                                label={b.description}
                                value={newBranch.description}
                                placeholder={b.branchDescriptionPlaceholder}
                                onChange={(description) => setNewBranch((current) => ({ ...current, description }))}
                            />
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setAddBranchOpen(false)}>
                            {b.cancel}
                        </Button>
                        <Button type="button" disabled={saving || newBranch.name.trim() === ''} onClick={() => void createBranch()}>
                            {saving ? i18n.common.saving : b.add}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {/* 2) Add department · 3) Edit department */}
            {departmentDraft !== null ? (
                <RegistrySheetDialog
                    title={departmentDraft.id === null ? b.addDepartment : b.editDepartment}
                    className="sis-branches-sheet"
                    onClose={() => setDepartmentDraft(null)}
                >
                    <SheetSection id="branches-department" title={departmentDraft.id === null ? b.addDepartment : b.editDepartment}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryTextField
                                label={b.departmentName}
                                editing
                                required
                                value={departmentDraft.name}
                                onChange={(name) => setDepartmentDraft((current) => (current === null ? current : { ...current, name }))}
                                fieldClassName="sis-branches-field--wide"
                            />
                            <DescriptionField
                                label={b.description}
                                value={departmentDraft.description}
                                placeholder={b.departmentDescriptionPlaceholder}
                                onChange={(description) =>
                                    setDepartmentDraft((current) => (current === null ? current : { ...current, description }))
                                }
                            />
                            <RegistryListField
                                label={b.branch}
                                editing
                                required
                                value={departmentDraft.branch_id}
                                display={branches.find((branch) => String(branch.id) === departmentDraft.branch_id)?.name ?? '—'}
                                options={branchOptions}
                                onChange={(branch_id) =>
                                    setDepartmentDraft((current) => (current === null ? current : { ...current, branch_id }))
                                }
                                fieldClassName="sis-branches-field--wide"
                            />
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setDepartmentDraft(null)}>
                            {b.cancel}
                        </Button>
                        <Button
                            type="button"
                            disabled={saving || departmentDraft.name.trim() === '' || departmentDraft.branch_id === ''}
                            onClick={saveDepartment}
                        >
                            {saving ? i18n.common.saving : departmentDraft.id === null ? b.add : b.saveChanges}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {/* 4) Manage a branch's departments: pick a branch → tick departments → delete (with confirmation). */}
            {manageOpen ? (
                <RegistrySheetDialog title={b.manageTitle} className="sis-branches-sheet sis-branches-manage" onClose={() => setManageOpen(false)}>
                    <SheetSection id="branches-manage-branch" title={b.branch}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryListField
                                label={b.chooseBranch}
                                editing
                                required
                                value={manageBranchId}
                                display={manageBranch?.name ?? '—'}
                                options={branchOptions}
                                onChange={(next) => {
                                    setManageBranchId(next);
                                    setManageChecked([]);
                                    setManageSearch('');
                                }}
                                fieldClassName="sis-branches-field--wide"
                            />
                        </div>
                    </SheetSection>

                    <SheetSection
                        id="branches-manage-departments"
                        title={`${b.branchDepartments} (${manageBranch?.departments.length ?? 0})`}
                    >
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <label className="sis-admission-sheet__field sis-branches-field--wide">
                                <span className="sis-admission-sheet__label">{b.searchInBranch}</span>
                                <span className="sis-branches-search">
                                    <Search aria-hidden className="sis-branches-search__icon" />
                                    <input
                                        className="sis-branches-search__input"
                                        value={manageSearch}
                                        placeholder={b.searchDepartments}
                                        dir="rtl"
                                        onChange={(event) => setManageSearch(event.target.value)}
                                    />
                                </span>
                            </label>

                            <div className="sis-branches-checklist-bar sis-branches-field--wide">
                                <label className="sis-branches-checklist__item sis-branches-checklist__item--all">
                                    <Checkbox
                                        checked={
                                            manageDepartments.length > 0 &&
                                            manageDepartments.every((department) => manageChecked.includes(department.id))
                                        }
                                        disabled={manageDepartments.length === 0}
                                        onCheckedChange={(checked) =>
                                            setManageChecked(checked === true ? manageDepartments.map((department) => department.id) : [])
                                        }
                                    />
                                    <span>{b.selectAll}</span>
                                </label>
                                <span className="sis-branches-checklist-bar__count" aria-live="polite">
                                    {b.selectedCount}: {manageChecked.length} {b.of} {manageBranch?.departments.length ?? 0}
                                </span>
                            </div>

                            <ul className="sis-branches-checklist sis-branches-field--wide">
                                {manageDepartments.length === 0 ? (
                                    <li className="sis-branches-empty">
                                        {manageSearch.trim() !== '' ? b.noSearchDepartments : b.noDepartments}
                                    </li>
                                ) : (
                                    manageDepartments.map((department) => (
                                        <li key={department.id}>
                                            <label
                                                className={`sis-branches-checklist__item${
                                                    manageChecked.includes(department.id) ? ' is-checked' : ''
                                                }`}
                                            >
                                                <Checkbox
                                                    checked={manageChecked.includes(department.id)}
                                                    onCheckedChange={(checked) =>
                                                        setManageChecked((current) =>
                                                            checked === true
                                                                ? [...current, department.id]
                                                                : current.filter((id) => id !== department.id),
                                                        )
                                                    }
                                                />
                                                <span className="sis-branches-checklist__text">
                                                    <span className="sis-branches-checklist__name">{department.name}</span>
                                                    {department.description ? (
                                                        <span className="sis-branches-checklist__desc">{department.description}</span>
                                                    ) : null}
                                                </span>
                                            </label>
                                        </li>
                                    ))
                                )}
                            </ul>
                        </div>
                    </SheetSection>

                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setManageOpen(false)}>
                            {b.close}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={saving || manageBranch === null}
                            onClick={() => openAddDepartment(manageBranch?.id ?? null)}
                        >
                            <Plus aria-hidden />
                            {b.addDepartmentToBranch}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            className="sis-branches-delete-btn"
                            disabled={saving || manageChecked.length === 0}
                            onClick={() => setConfirmBulkDelete(true)}
                        >
                            <Trash2 aria-hidden />
                            {b.deleteSelected} ({manageChecked.length})
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            <ConfirmDialog
                open={confirmBulkDelete}
                title={b.deleteDepartmentTitle}
                description={`${b.deleteSelectedConfirm} (${manageChecked.length})`}
                confirmLabel={b.deleteSelected}
                tone="danger"
                confirmPending={saving}
                onConfirm={() =>
                    void deleteDepartments(manageChecked, () => {
                        setManageChecked([]);
                        setConfirmBulkDelete(false);
                    })
                }
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setConfirmBulkDelete(false);
                    }
                }}
            />

            <ConfirmDialog
                open={deleteBranch !== null}
                title={b.deleteBranchTitle}
                description={b.deleteBranchConfirm}
                confirmLabel={b.deleteBranch}
                tone="danger"
                confirmPending={saving}
                onConfirm={() =>
                    deleteBranch &&
                    void run(
                        () => request('post', `/organization/branches/${deleteBranch.id}/delete`),
                        () => setDeleteBranch(null),
                    )
                }
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setDeleteBranch(null);
                    }
                }}
            />
            <ConfirmDialog
                open={deleteDepartment !== null}
                title={b.deleteDepartmentTitle}
                description={b.deleteDepartmentConfirm}
                confirmLabel={b.deleteDepartment}
                tone="danger"
                confirmPending={saving}
                onConfirm={() => deleteDepartment && void deleteDepartments([deleteDepartment.id], () => setDeleteDepartment(null))}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setDeleteDepartment(null);
                    }
                }}
            />
        </>
    );
}
