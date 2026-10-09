import { Head, router } from '@inertiajs/react';
import {
    ArrowDownWideNarrow,
    ArrowUpNarrowWide,
    CalendarRange,
    CheckCircle2,
    CircleSlash,
    Copy,
    DoorOpen,
    Eye,
    FlaskConical,
    Layers,
    Palette,
    Pencil,
    PlusCircle,
    RotateCcw,
    Shapes,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { RegistryListField, RegistrySheetDialog, RegistryTextField, useRegistryRequest } from '@/components/organization/registry-sheet';
import { AppearanceDialog, AppearanceFields, hueStyle, type AppearanceValue } from '@/components/sis/appearance-fields';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { isContextMenuKey, SisContextMenu, useContextMenu, type ContextMenuItem } from '@/components/sis/context-menu';
import { useRegisterPageRibbon, type PageRibbonCommand, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Room = {
    id: number;
    branch_id: number;
    branch_name: string;
    code: string;
    name: string;
    abbreviation: string | null;
    abbreviation_suggested: string | null;
    color_hue: number | null;
    room_number: string | null;
    room_type_id: number | null;
    type_name: string | null;
    type_kind: number | null;
    capacity: number | null;
    building: string | null;
    floor: number | null;
    location: string | null;
    department_id: number | null;
    department_name: string | null;
    supports_practical: boolean;
    equipment: string | null;
    suitable_for: string | null;
    notes: string | null;
    status: number;
    weekly_lessons: number;
    links: number;
};

type RoomType = {
    id: number;
    school_id: number | null;
    code: string;
    name: string;
    abbreviation: string | null;
    kind: number;
    supports_practical: boolean;
    color_hue: number | null;
    status: number;
    rooms: number;
};

type Filters = {
    search: string;
    branch_id: number | null;
    room_type_id: number | null;
    state: number | null;
    practical: boolean | null;
    sort: string;
    direction: 'asc' | 'desc';
};

type Props = {
    rooms: Room[];
    types: RoomType[];
    branches: Array<{ id: number; name: string; departments: Array<{ id: number; name: string }> }>;
    stats: { total: number; active: number; practical: number; capacity: number; by_kind: Record<string, number> };
    pagination: { page: number; per_page: number; total: number; last_page: number };
    filters: Filters;
    authorization: { can_manage: boolean };
};

type RoomForm = {
    branch_id: string;
    code: string;
    name: string;
    room_number: string;
    room_type_id: string;
    capacity: string;
    building: string;
    floor: string;
    location: string;
    department_id: string;
    supports_practical: boolean;
    equipment: string;
    suitable_for: string;
    notes: string;
    appearance: AppearanceValue;
};

type TypeForm = { id: number | null; code: string; name: string; kind: string; supports_practical: boolean; appearance: AppearanceValue };

const ACTIVE = 1;
const RELOAD = ['rooms', 'types', 'stats', 'pagination', 'flash'];
const KINDS = [1, 2, 3, 4, 9] as const;
const SORTABLE: Array<{ key: string; label: (r: ReturnType<typeof t>['rooms']) => string; num?: boolean }> = [
    { key: 'code', label: (r) => r.code },
    { key: 'name', label: (r) => r.name },
    { key: 'room_number', label: (r) => r.roomNumber },
    { key: 'type', label: (r) => r.type },
    { key: 'capacity', label: (r) => r.capacity, num: true },
    { key: 'building', label: (r) => r.building },
    { key: 'floor', label: (r) => r.floor, num: true },
    { key: 'branch', label: (r) => r.branch },
];

const text = (value: string | null | undefined) => value ?? '';
const nullable = (value: string) => (value.trim() === '' ? null : value.trim());
const intOrNull = (value: string) => (value.trim() === '' ? null : Number(value));

function formOf(room: Room | null, branchId: number | null): RoomForm {
    return {
        branch_id: String(room?.branch_id ?? branchId ?? ''),
        code: room?.code ?? '',
        name: room?.name ?? '',
        room_number: text(room?.room_number),
        room_type_id: room?.room_type_id === null || room === null ? '' : String(room.room_type_id),
        capacity: room?.capacity === null || room === null ? '' : String(room.capacity),
        building: text(room?.building),
        floor: room?.floor === null || room === null ? '' : String(room.floor),
        location: text(room?.location),
        department_id: room?.department_id === null || room === null ? '' : String(room.department_id),
        supports_practical: room?.supports_practical ?? false,
        equipment: text(room?.equipment),
        suitable_for: text(room?.suitable_for),
        notes: text(room?.notes),
        appearance: { abbreviation: text(room?.abbreviation), color_hue: room?.color_hue ?? null },
    };
}

/** The pages to show around the current one (1 … n with a window). */
function visiblePages(current: number, last: number): number[] {
    const start = Math.max(1, Math.min(current - 2, last - 4));

    return Array.from({ length: Math.min(5, last) }, (_, index) => start + index).filter((page) => page <= last);
}

export default function OrganizationRooms(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().rooms.title, href: '/organization/rooms' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <RoomsPage {...props} />
        </AppLayout>
    );
}

function RoomsPage({ rooms, types, branches, stats, pagination, filters, authorization }: Props) {
    const i18n = t();
    const r = i18n.rooms;
    const canManage = authorization.can_manage;
    const request = useRegistryRequest(RELOAD);
    const menu = useContextMenu<Room>();
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [sheet, setSheet] = useState<{ id: number | null; viewOnly: boolean; form: RoomForm } | null>(null);
    const [typesOpen, setTypesOpen] = useState(false);
    const [appearanceFor, setAppearanceFor] = useState<Room | null>(null);
    const [confirmOut, setConfirmOut] = useState<Room | null>(null);
    const [saving, setSaving] = useState(false);

    const selected = rooms.find((room) => room.id === selectedId) ?? null;
    const activeTypes = types.filter((type) => type.status === ACTIVE);
    const typeById = useMemo(() => new Map(types.map((type) => [type.id, type])), [types]);

    /** Server-side list: every filter / sort / page change is a visit (no client filtering of the catalogue). */
    const visit = (patch: Partial<Filters> & { page?: number }) => {
        const next = { ...filters, ...patch };
        router.get(
            '/organization/rooms',
            {
                search: next.search || undefined,
                branch_id: next.branch_id ?? undefined,
                room_type_id: next.room_type_id ?? undefined,
                state: next.state ?? undefined,
                practical: next.practical === null ? undefined : next.practical ? 1 : 0,
                sort: next.sort,
                direction: next.direction,
                page: patch.page ?? 1,
            },
            { preserveState: true, preserveScroll: true, replace: true, only: ['rooms', 'pagination', 'filters', 'stats', 'types'] },
        );
    };

    const run = async (action: () => Promise<boolean>, after?: () => void) => {
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

    const openRoom = (room: Room | null, viewOnly: boolean) => setSheet({ id: room?.id ?? null, viewOnly, form: formOf(room, filters.branch_id ?? branches[0]?.id ?? null) });
    const setStatus = (room: Room, active: boolean, after?: () => void) => run(() => request('post', `/organization/rooms/${room.id}/status`, { active: active ? 1 : 0 }), after);
    const openTimetable = (room: Room) => router.get('/timetable', { view: 'room', room_id: room.id });

    const saveRoom = () => {
        if (sheet === null) {
            return;
        }
        const f = sheet.form;
        const payload = {
            name: f.name.trim(),
            abbreviation: nullable(f.appearance.abbreviation),
            color_hue: f.appearance.color_hue,
            room_number: nullable(f.room_number),
            room_type_id: intOrNull(f.room_type_id),
            capacity: intOrNull(f.capacity),
            building: nullable(f.building),
            floor: intOrNull(f.floor),
            location: nullable(f.location),
            department_id: intOrNull(f.department_id),
            supports_practical: f.supports_practical,
            equipment: nullable(f.equipment),
            suitable_for: nullable(f.suitable_for),
            notes: nullable(f.notes),
        };
        void run(
            () => (sheet.id === null ? request('post', '/organization/rooms', { ...payload, branch_id: Number(f.branch_id), code: f.code.trim() }) : request('patch', `/organization/rooms/${sheet.id}`, payload)),
            () => setSheet(null),
        );
    };

    const menuItems = (room: Room): ContextMenuItem[] => [
        { id: 'view', label: r.view, icon: Eye, onSelect: () => openRoom(room, true) },
        ...(canManage
            ? [
                  { id: 'edit', label: r.edit, icon: Pencil, onSelect: () => openRoom(room, false) },
                  { id: 'appearance', label: i18n.appearance.edit, icon: Palette, onSelect: () => setAppearanceFor(room) },
              ]
            : []),
        { id: 'copy', label: r.copyCode, icon: Copy, onSelect: () => void navigator.clipboard?.writeText(room.code) },
        { id: 'timetable', label: r.openTimetable, icon: CalendarRange, separator: true, onSelect: () => openTimetable(room) },
        ...(canManage
            ? [
                  room.status === ACTIVE
                      ? { id: 'out', label: r.deactivate, icon: Trash2, danger: true, separator: true, disabled: room.links + room.weekly_lessons > 0, onSelect: () => setConfirmOut(room) }
                      : { id: 'in', label: r.reactivate, icon: RotateCcw, separator: true, onSelect: () => void setStatus(room, true) },
              ]
            : []),
    ];

    // ── Ribbon «تحرير» ─────────────────────────────────────────────────────────────
    const ribbon = useMemo((): PageRibbonGroup[] => {
        const none = selected === null;
        const field = (value: string, label: string, options: Array<{ value: string; label: string }>, onChange: (next: string) => void) => (
            <div className="sis-ribbon__filter-field" dir="rtl">
                <span className="sis-admission-select-fit">
                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                        {options.find((o) => o.value === value)?.label ?? options[0]?.label ?? ''}
                    </span>
                    <SisListSelect value={value} options={options} onChange={onChange} triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control" dir="rtl" ariaLabel={label} />
                </span>
            </div>
        );

        return [
            {
                id: 'rooms-actions',
                label: r.actions,
                commands: [
                    { id: 'rooms-view', label: i18n.common.view, icon: Eye, disabled: none, title: none ? i18n.orgRibbon.needsSelection : r.view, onSelect: () => selected && openRoom(selected, true) },
                    ...(canManage
                        ? [
                              { id: 'rooms-add', label: r.add, icon: PlusCircle, disabled: branches.length === 0, onSelect: () => openRoom(null, false) },
                              { id: 'rooms-edit', label: i18n.common.edit, icon: Pencil, tone: 'edit' as const, disabled: none || saving, onSelect: () => selected && openRoom(selected, false) },
                              { id: 'rooms-appearance', label: i18n.appearance.title, icon: Palette, disabled: none, onSelect: () => selected && setAppearanceFor(selected) },
                              selected === null || selected.status === ACTIVE
                                  ? { id: 'rooms-out', label: r.deactivate, icon: Trash2, tone: 'delete' as const, disabled: none || saving, onSelect: () => selected && setConfirmOut(selected) }
                                  : { id: 'rooms-in', label: r.reactivate, icon: RotateCcw, disabled: saving, onSelect: () => void setStatus(selected, true) },
                          ]
                        : []),
                    { id: 'rooms-timetable', label: r.openTimetable, icon: CalendarRange, disabled: none, onSelect: () => selected && openTimetable(selected) },
                ],
            },
            {
                id: 'rooms-state',
                label: r.filters,
                commands: [
                    { key: null, label: r.all, icon: Layers, count: stats.total },
                    { key: 1, label: r.active, icon: CheckCircle2, count: stats.active },
                    { key: 2, label: r.inactive, icon: CircleSlash, count: stats.total - stats.active },
                ]
                    .map((tab): PageRibbonCommand => ({
                        id: `rooms-state-${tab.key ?? 'all'}`,
                        label: tab.label,
                        icon: tab.icon,
                        count: tab.count,
                        pressed: filters.state === tab.key,
                        onSelect: () => visit({ state: tab.key }),
                    }))
                    .concat([
                        {
                            id: 'rooms-practical',
                            label: r.practicalOnly,
                            icon: FlaskConical,
                            count: stats.practical,
                            pressed: filters.practical === true,
                            onSelect: () => visit({ practical: filters.practical === true ? null : true }),
                        } satisfies PageRibbonCommand,
                    ]),
            },
            {
                id: 'rooms-filters',
                label: r.branch,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" dir="rtl">
                        <div className="sis-ribbon__filters-stack">
                            {field(filters.branch_id === null ? '' : String(filters.branch_id), r.branch, [{ value: '', label: r.allBranches }, ...branches.map((b) => ({ value: String(b.id), label: b.name }))], (next) =>
                                visit({ branch_id: next === '' ? null : Number(next) }),
                            )}
                            {field(filters.room_type_id === null ? '' : String(filters.room_type_id), r.type, [{ value: '', label: r.allTypes }, ...types.map((x) => ({ value: String(x.id), label: x.name }))], (next) =>
                                visit({ room_type_id: next === '' ? null : Number(next) }),
                            )}
                        </div>
                    </div>
                ),
            },
            {
                id: 'rooms-manage',
                label: r.manage,
                commands: [{ id: 'rooms-types', label: r.types, icon: Shapes, count: types.length, onSelect: () => setTypesOpen(true) }],
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [i18n, selected, canManage, saving, filters, stats, branches, types]);
    useRegisterPageRibbon('edit', ribbon);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: filters.search,
            label: r.search,
            placeholder: r.search,
            onDraftChange: () => undefined,
            onCommit: (query: string) => visit({ search: query.trim() }),
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters, r.search],
    );
    useRegisterPageTitlebarSearch(titlebarSearch);

    const sortBy = (key: string) => visit({ sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc' });
    const kindLabel = (kind: number | null) => (kind === null ? '' : (r.kinds[kind] ?? ''));
    const offset = (pagination.page - 1) * pagination.per_page;

    return (
        <>
            <Head title={r.title} />
            <div className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page flex h-full min-h-0 flex-col overflow-hidden pb-4" dir="rtl" lang="ar">
                {canManage ? null : <p className="sis-branches-page__notice">{r.readOnly}</p>}
                <div className="sis-admission-page-body">
                    <section aria-label={r.tableCaption} className="flex min-h-0 flex-1 flex-col">
                        <p className="sis-timetable-toolbar__meta" aria-label={r.stats}>
                            {r.statsTotal}: <bdi dir="ltr">{stats.total}</bdi> · {r.statsActive}: <bdi dir="ltr">{stats.active}</bdi> · {r.statsPractical}: <bdi dir="ltr">{stats.practical}</bdi> · {r.statsCapacity}:{' '}
                            <bdi dir="ltr">{stats.capacity}</bdi>
                        </p>
                        {rooms.length === 0 ? (
                            <p className="text-sm">{stats.total === 0 ? r.empty : r.noResult}</p>
                        ) : (
                            <>
                                <div className="sis-admission-periods-table sis-admission-drafts-table">
                                    <div className="sis-admission-drafts-table__scroller" data-allow-x-scroll>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th className="sis-admission-drafts-table__num">#</th>
                                                    {SORTABLE.map((column) => (
                                                        <th key={column.key} className={column.num ? 'sis-admission-drafts-table__num' : undefined} aria-sort={filters.sort === column.key ? (filters.direction === 'asc' ? 'ascending' : 'descending') : 'none'}>
                                                            <button type="button" className="sis-ops-hub__link" onClick={() => sortBy(column.key)}>
                                                                {column.label(r)}
                                                                {filters.sort === column.key ? (
                                                                    filters.direction === 'asc' ? (
                                                                        <ArrowUpNarrowWide aria-hidden width={12} height={12} />
                                                                    ) : (
                                                                        <ArrowDownWideNarrow aria-hidden width={12} height={12} />
                                                                    )
                                                                ) : null}
                                                            </button>
                                                        </th>
                                                    ))}
                                                    <th>{r.practical}</th>
                                                    <th className="sis-admission-drafts-table__num">{r.weeklyLessons}</th>
                                                    <th>{r.status}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {rooms.map((room, index) => (
                                                    <tr
                                                        key={room.id}
                                                        tabIndex={0}
                                                        className={selectedId === room.id ? 'sis-admission-periods-table__row--selected' : undefined}
                                                        aria-selected={selectedId === room.id}
                                                        onClick={() => setSelectedId(room.id)}
                                                        onDoubleClick={() => openRoom(room, !canManage)}
                                                        onContextMenu={(event) => {
                                                            setSelectedId(room.id);
                                                            menu.open(event, room);
                                                        }}
                                                        onKeyDown={(event) => {
                                                            if (isContextMenuKey(event)) {
                                                                event.preventDefault();
                                                                setSelectedId(room.id);
                                                                menu.openAt(event.currentTarget, room);
                                                            } else if (event.key === 'Enter') {
                                                                openRoom(room, !canManage);
                                                            }
                                                        }}
                                                    >
                                                        <td className="sis-admission-drafts-table__num">
                                                            <span dir="ltr">{offset + index + 1}</span>
                                                        </td>
                                                        <td>
                                                            <span className="sis-timetable-card sis-timetable-card--tray" style={hueStyle(room.color_hue ?? typeById.get(room.room_type_id ?? 0)?.color_hue ?? 205)}>
                                                                <span className="sis-timetable-card__subject" dir="ltr">
                                                                    {room.abbreviation ?? room.code}
                                                                </span>
                                                            </span>
                                                        </td>
                                                        <td>{room.name}</td>
                                                        <td dir="ltr">{room.room_number ?? '—'}</td>
                                                        <td>{room.type_name === null ? r.noType : `${room.type_name} · ${kindLabel(room.type_kind)}`}</td>
                                                        <td className="sis-admission-drafts-table__num">{room.capacity ?? '—'}</td>
                                                        <td>{room.building ?? '—'}</td>
                                                        <td className="sis-admission-drafts-table__num">{room.floor ?? '—'}</td>
                                                        <td>{room.department_name === null ? room.branch_name : `${room.branch_name} › ${room.department_name}`}</td>
                                                        <td>{room.supports_practical ? r.practicalYes : '—'}</td>
                                                        <td className="sis-admission-drafts-table__num">{room.weekly_lessons}</td>
                                                        <td>
                                                            <span className={`sis-branches-status${room.status === ACTIVE ? '' : ' sis-org-status--inactive'}`}>{room.status === ACTIVE ? r.inService : r.outOfService}</span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                {pagination.last_page > 1 ? (
                                    <nav className="sis-admission-drafts-pagination" aria-label={i18n.common.page}>
                                        <ul className="sis-admission-pagination" dir="ltr">
                                            <li className="sis-admission-pagination__item">
                                                <button type="button" className="sis-admission-pagination__link" aria-label={i18n.common.previous} disabled={pagination.page <= 1} onClick={() => visit({ page: pagination.page - 1 })}>
                                                    <span aria-hidden="true">&laquo;</span>
                                                </button>
                                            </li>
                                            {visiblePages(pagination.page, pagination.last_page).map((pageNum) => (
                                                <li key={pageNum} className="sis-admission-pagination__item">
                                                    <button
                                                        type="button"
                                                        className={pageNum === pagination.page ? 'sis-admission-pagination__link sis-admission-pagination__link--active' : 'sis-admission-pagination__link'}
                                                        aria-current={pageNum === pagination.page ? 'page' : undefined}
                                                        onClick={() => visit({ page: pageNum })}
                                                    >
                                                        {pageNum}
                                                    </button>
                                                </li>
                                            ))}
                                            <li className="sis-admission-pagination__item">
                                                <button type="button" className="sis-admission-pagination__link" aria-label={i18n.common.next} disabled={pagination.page >= pagination.last_page} onClick={() => visit({ page: pagination.page + 1 })}>
                                                    <span aria-hidden="true">&raquo;</span>
                                                </button>
                                            </li>
                                        </ul>
                                    </nav>
                                ) : null}
                            </>
                        )}
                    </section>
                </div>
            </div>

            <SisContextMenu controller={menu} items={menuItems} label={r.menu} />

            {sheet !== null ? (
                <RoomSheet
                    sheet={sheet}
                    branches={branches}
                    types={activeTypes}
                    saving={saving}
                    canManage={canManage}
                    room={rooms.find((room) => room.id === sheet.id) ?? null}
                    onChange={(form) => setSheet((current) => (current === null ? current : { ...current, form }))}
                    onEdit={() => setSheet((current) => (current === null ? current : { ...current, viewOnly: false }))}
                    onSave={saveRoom}
                    onClose={() => setSheet(null)}
                />
            ) : null}

            {typesOpen ? <RoomTypesSheet types={types} canManage={canManage} onClose={() => setTypesOpen(false)} /> : null}

            {appearanceFor !== null ? (
                <AppearanceDialog
                    title={i18n.appearance.edit}
                    entityName={`${appearanceFor.code} — ${appearanceFor.name}`}
                    initial={{ abbreviation: text(appearanceFor.abbreviation), color_hue: appearanceFor.color_hue }}
                    suggested={appearanceFor.abbreviation_suggested}
                    defaultHue={typeById.get(appearanceFor.room_type_id ?? 0)?.color_hue ?? null}
                    url="/organization/appearance"
                    payload={{ target: 'room', id: appearanceFor.id }}
                    reloadProps={RELOAD}
                    canEdit={canManage}
                    onClose={() => setAppearanceFor(null)}
                />
            ) : null}

            <ConfirmDialog
                open={confirmOut !== null}
                title={r.deactivate}
                description={r.deactivateConfirm}
                confirmLabel={r.deactivate}
                tone="danger"
                confirmPending={saving}
                onConfirm={() => confirmOut !== null && void setStatus(confirmOut, false, () => setConfirmOut(null))}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setConfirmOut(null);
                    }
                }}
            />
        </>
    );
}

function TextArea({ label, value, onChange, editing }: { label: string; value: string; onChange: (value: string) => void; editing: boolean }) {
    return (
        <label className="sis-admission-sheet__field sis-branches-field--wide">
            <span className="sis-admission-sheet__label">{label}</span>
            {editing ? (
                <textarea className="sis-admission-sheet__control sis-branches-textarea" value={value} rows={3} dir="rtl" onChange={(event) => onChange(event.target.value)} />
            ) : (
                <div className="sis-admission-sheet__control sis-admission-draft-readonly">{value.trim() === '' ? '—' : value}</div>
            )}
        </label>
    );
}

/** «بيانات الغرفة»: add / view / edit one room (code and branch are fixed after creation). */
function RoomSheet({
    sheet,
    branches,
    types,
    saving,
    canManage,
    room,
    onChange,
    onEdit,
    onSave,
    onClose,
}: {
    sheet: { id: number | null; viewOnly: boolean; form: RoomForm };
    branches: Props['branches'];
    types: RoomType[];
    saving: boolean;
    canManage: boolean;
    room: Room | null;
    onChange: (form: RoomForm) => void;
    onEdit: () => void;
    onSave: () => void;
    onClose: () => void;
}) {
    const i18n = t();
    const r = i18n.rooms;
    const editing = !sheet.viewOnly;
    const f = sheet.form;
    const set = <K extends keyof RoomForm>(key: K) => (value: RoomForm[K]) => onChange({ ...f, [key]: value });
    const branch = branches.find((b) => String(b.id) === f.branch_id) ?? null;
    const typeOptions = [{ value: '', label: r.noType }, ...types.map((type) => ({ value: String(type.id), label: `${type.name} · ${r.kinds[type.kind] ?? ''}` }))];
    const departmentOptions = [{ value: '', label: r.noDepartment }, ...(branch?.departments ?? []).map((d) => ({ value: String(d.id), label: d.name }))];
    const valid = f.name.trim() !== '' && (sheet.id !== null || (f.code.trim() !== '' && f.branch_id !== '')) && f.appearance.abbreviation.trim().length <= 20;
    const chosenType = types.find((type) => String(type.id) === f.room_type_id) ?? null;

    return (
        <RegistrySheetDialog title={sheet.id === null ? r.add : sheet.viewOnly ? r.view : r.edit} className="sis-branches-sheet" onClose={onClose}>
            <SheetSection id="room-basic" title={r.sectionBasic}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <RegistryListField
                        label={r.branch}
                        editing={editing && sheet.id === null}
                        required
                        value={f.branch_id}
                        display={branch?.name ?? room?.branch_name ?? '—'}
                        options={branches.map((b) => ({ value: String(b.id), label: b.name }))}
                        onChange={(value) => onChange({ ...f, branch_id: value, department_id: '' })}
                        fieldClassName="sis-branches-field--wide"
                    />
                    <RegistryTextField label={r.code} editing={editing && sheet.id === null} required dir="ltr" value={f.code} onChange={set('code')} />
                    <RegistryTextField label={r.roomNumber} editing={editing} dir="ltr" value={f.room_number} onChange={set('room_number')} />
                    <RegistryTextField label={r.name} editing={editing} required value={f.name} onChange={set('name')} fieldClassName="sis-branches-field--wide" />
                    <RegistryListField
                        label={r.type}
                        editing={editing}
                        value={f.room_type_id}
                        display={typeOptions.find((o) => o.value === f.room_type_id)?.label ?? room?.type_name ?? r.noType}
                        options={typeOptions}
                        onChange={(value) => {
                            const type = types.find((x) => String(x.id) === value);
                            onChange({ ...f, room_type_id: value, supports_practical: type === undefined ? f.supports_practical : type.supports_practical });
                        }}
                    />
                    <RegistryTextField label={`${r.capacity} (${r.capacityHint})`} editing={editing} type="number" dir="ltr" value={f.capacity} onChange={set('capacity')} />
                    <label className="sis-admission-sheet__field sis-branches-field--wide">
                        <span className="sis-admission-sheet__label">{r.practical}</span>
                        <span>
                            <input type="checkbox" checked={f.supports_practical} disabled={!editing} onChange={(event) => set('supports_practical')(event.target.checked)} />{' '}
                            {chosenType === null ? r.practical : `${r.kinds[chosenType.kind] ?? ''} — ${chosenType.kind === 2 ? r.isLab : chosenType.kind === 3 ? r.isWorkshop : chosenType.kind === 4 ? r.isHall : chosenType.kind === 1 ? r.isClassroom : ''}`}
                        </span>
                    </label>
                </div>
            </SheetSection>
            <SheetSection id="room-place" title={r.sectionPlace}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <RegistryTextField label={r.building} editing={editing} value={f.building} onChange={set('building')} />
                    <RegistryTextField label={r.floor} editing={editing} type="number" dir="ltr" value={f.floor} onChange={set('floor')} />
                    <RegistryTextField label={r.location} editing={editing} value={f.location} onChange={set('location')} fieldClassName="sis-branches-field--wide" />
                    <RegistryListField
                        label={r.department}
                        editing={editing}
                        value={f.department_id}
                        display={departmentOptions.find((o) => o.value === f.department_id)?.label ?? room?.department_name ?? r.noDepartment}
                        options={departmentOptions}
                        onChange={set('department_id')}
                        fieldClassName="sis-branches-field--wide"
                    />
                </div>
            </SheetSection>
            <SheetSection id="room-use" title={r.sectionUse}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <TextArea label={r.equipment} editing={editing} value={f.equipment} onChange={set('equipment')} />
                    <TextArea label={r.suitableFor} editing={editing} value={f.suitable_for} onChange={set('suitable_for')} />
                    <TextArea label={r.notes} editing={editing} value={f.notes} onChange={set('notes')} />
                    {room !== null ? (
                        <p className="sis-timetable-sheet__hint sis-branches-field--wide">
                            {r.weeklyLessons}: <bdi dir="ltr">{room.weekly_lessons}</bdi>
                        </p>
                    ) : null}
                </div>
            </SheetSection>
            <SheetSection id="room-appearance" title={r.sectionAppearance}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <AppearanceFields
                        value={f.appearance}
                        onChange={set('appearance')}
                        editing={editing}
                        suggested={room?.abbreviation_suggested ?? (f.room_number.trim() || f.code.trim() || null)}
                        defaultHue={chosenType?.color_hue ?? null}
                        previewTitle={f.name}
                    />
                </div>
            </SheetSection>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" disabled={saving} onClick={onClose}>
                    {sheet.viewOnly ? r.close : r.cancel}
                </Button>
                {sheet.viewOnly ? (
                    canManage ? (
                        <Button type="button" onClick={onEdit}>
                            <Pencil aria-hidden />
                            {i18n.common.edit}
                        </Button>
                    ) : null
                ) : (
                    <Button type="button" disabled={saving || !valid} onClick={onSave}>
                        {saving ? i18n.common.saving : r.save}
                    </Button>
                )}
            </div>
        </RegistrySheetDialog>
    );
}

/** «أنواع الغرف»: system types (shared, read-only) + the school's own types (add / edit / retire). */
function RoomTypesSheet({ types, canManage, onClose }: { types: RoomType[]; canManage: boolean; onClose: () => void }) {
    const i18n = t();
    const r = i18n.rooms;
    const request = useRegistryRequest(RELOAD);
    const [form, setForm] = useState<TypeForm | null>(null);
    const [saving, setSaving] = useState(false);
    const kindOptions = KINDS.map((kind) => ({ value: String(kind), label: r.kinds[kind] ?? String(kind) }));

    const save = async () => {
        if (form === null) {
            return;
        }
        setSaving(true);
        const payload = {
            name: form.name.trim(),
            kind: Number(form.kind),
            supports_practical: form.supports_practical,
            abbreviation: nullable(form.appearance.abbreviation),
            color_hue: form.appearance.color_hue,
        };
        const ok = form.id === null ? await request('post', '/organization/room-types', { ...payload, code: form.code.trim() }) : await request('patch', `/organization/room-types/${form.id}`, payload);
        setSaving(false);
        if (ok) {
            setForm(null);
        }
    };
    const toggle = async (type: RoomType) => {
        setSaving(true);
        await request('post', `/organization/room-types/${type.id}/status`, { active: type.status === ACTIVE ? 0 : 1 });
        setSaving(false);
    };

    return (
        <RegistrySheetDialog title={r.types} className="sis-branches-sheet" onClose={onClose}>
            <SheetSection id="room-types" title={r.typesHint}>
                <div className="sis-admission-periods-table sis-admission-drafts-table sis-branches-field--wide">
                    <div className="sis-admission-drafts-table__scroller" data-allow-x-scroll>
                        <table>
                            <thead>
                                <tr>
                                    <th>{r.abbreviationColumn}</th>
                                    <th>{r.typeName}</th>
                                    <th>{r.kind}</th>
                                    <th>{r.practical}</th>
                                    <th className="sis-admission-drafts-table__num">{r.typeRooms}</th>
                                    <th>{r.status}</th>
                                    <th aria-hidden="true" />
                                </tr>
                            </thead>
                            <tbody>
                                {types.map((type) => (
                                    <tr key={type.id}>
                                        <td>
                                            <span className="sis-timetable-card sis-timetable-card--tray" style={hueStyle(type.color_hue ?? 205)}>
                                                <span className="sis-timetable-card__subject">{type.abbreviation ?? type.code}</span>
                                            </span>
                                        </td>
                                        <td>
                                            {type.name}
                                            {type.school_id === null ? ` · ${r.systemType}` : ''}
                                        </td>
                                        <td>{r.kinds[type.kind] ?? ''}</td>
                                        <td>{type.supports_practical ? r.practicalYes : '—'}</td>
                                        <td className="sis-admission-drafts-table__num">{type.rooms}</td>
                                        <td>
                                            <span className={`sis-branches-status${type.status === ACTIVE ? '' : ' sis-org-status--inactive'}`}>{type.status === ACTIVE ? r.inService : r.outOfService}</span>
                                        </td>
                                        <td className="sis-timetable-periods__actions">
                                            {canManage && type.school_id !== null ? (
                                                <>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={saving || form !== null}
                                                        onClick={() =>
                                                            setForm({ id: type.id, code: type.code, name: type.name, kind: String(type.kind), supports_practical: type.supports_practical, appearance: { abbreviation: type.abbreviation ?? '', color_hue: type.color_hue } })
                                                        }
                                                    >
                                                        {r.editType}
                                                    </Button>
                                                    <Button type="button" size="sm" variant="outline" disabled={saving || (type.status === ACTIVE && type.rooms > 0)} onClick={() => void toggle(type)}>
                                                        {type.status === ACTIVE ? r.deactivateType : r.reactivateType}
                                                    </Button>
                                                </>
                                            ) : canManage ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={saving || form !== null}
                                                    onClick={() =>
                                                        setForm({ id: null, code: `${type.code}_2`, name: type.name, kind: String(type.kind), supports_practical: type.supports_practical, appearance: { abbreviation: type.abbreviation ?? '', color_hue: type.color_hue } })
                                                    }
                                                >
                                                    {r.copyType}
                                                </Button>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </SheetSection>
            {form !== null ? (
                <SheetSection id="room-type-form" title={form.id === null ? r.addType : r.editType}>
                    <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                        <RegistryTextField label={r.typeCode} editing={form.id === null} required dir="ltr" value={form.code} onChange={(code) => setForm({ ...form, code })} />
                        <RegistryTextField label={r.typeName} editing required value={form.name} onChange={(name) => setForm({ ...form, name })} />
                        <RegistryListField
                            label={r.kind}
                            editing
                            required
                            value={form.kind}
                            display={kindOptions.find((o) => o.value === form.kind)?.label ?? ''}
                            options={kindOptions}
                            onChange={(kind) => setForm({ ...form, kind, supports_practical: kind === '2' || kind === '3' })}
                        />
                        <label className="sis-admission-sheet__field">
                            <span className="sis-admission-sheet__label">{r.practical}</span>
                            <input type="checkbox" checked={form.supports_practical} onChange={(event) => setForm({ ...form, supports_practical: event.target.checked })} />
                        </label>
                        <AppearanceFields value={form.appearance} onChange={(appearance) => setForm({ ...form, appearance })} editing previewTitle={form.name} />
                    </div>
                </SheetSection>
            ) : null}
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" disabled={saving} onClick={form === null ? onClose : () => setForm(null)}>
                    {form === null ? r.close : r.cancel}
                </Button>
                {canManage ? (
                    form === null ? (
                        <Button type="button" onClick={() => setForm({ id: null, code: '', name: '', kind: '1', supports_practical: false, appearance: { abbreviation: '', color_hue: null } })}>
                            <DoorOpen aria-hidden />
                            {r.addType}
                        </Button>
                    ) : (
                        <Button type="button" disabled={saving || form.name.trim() === '' || form.code.trim() === ''} onClick={() => void save()}>
                            {saving ? i18n.common.saving : r.save}
                        </Button>
                    )
                ) : null}
            </div>
        </RegistrySheetDialog>
    );
}
