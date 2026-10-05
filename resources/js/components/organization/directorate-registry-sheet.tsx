import { useEffect, useMemo, useRef, useState } from 'react';
import {
    blankToNull,
    RegistryActions,
    RegistryListField,
    RegistrySheetDialog,
    RegistryTextField,
    statusUrl as registryStatusUrl,
    useRegistryEditor,
    useRegistryRequest,
} from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { t } from '@/i18n';

export type DirectorateRegistryItem = {
    id: number;
    name: string;
    region: string | null;
    status: number;
    /** The user's schools in this directorate. */
    school_ids: number[];
    schools_count: number;
};

export type DirectorateRegistry = {
    directorates: DirectorateRegistryItem[];
    /** The user's schools — choices for "مدارسك فيها". */
    schools: { id: number; name: string; directorate_id: number }[];
};

type Draft = {
    name: string;
    region: string;
    status: '1' | '2';
    school_ids: number[];
};

type SchoolChoice = DirectorateRegistry['schools'][number];

/** The schools sheet's directorate list depends on these too. */
const RELOAD_PROPS = ['directorateRegistry', 'schoolRegistry', 'flash'];

const ACTIVE = '1';
const INACTIVE = '2';

function toDraft(item: DirectorateRegistryItem): Draft {
    return {
        name: item.name,
        region: item.region ?? '',
        status: item.status === 1 ? ACTIVE : INACTIVE,
        school_ids: [...item.school_ids],
    };
}

function sameIds(left: number[], right: number[]): boolean {
    return left.length === right.length && left.every((id) => right.includes(id));
}

type Props = {
    registry: DirectorateRegistry | null;
    canManage: boolean;
    onClose: () => void;
};

/** Directorate registry — all directorates + add / edit form (every field) in one sheet. */
export function DirectorateRegistrySheetDialog({ registry, canManage, onClose }: Props) {
    const i18n = t();
    const d = i18n.directorateRegistry;
    const rows = useMemo(() => registry?.directorates ?? [], [registry]);
    const schools = useMemo(() => registry?.schools ?? [], [registry]);
    const loading = registry === null;
    const [confirmDeactivate, setConfirmDeactivate] = useState(false);
    /** Inactive was chosen for a new directorate — applied once the created row is selected. */
    const deactivateAfterCreateRef = useRef(false);

    const editor = useRegistryEditor<DirectorateRegistryItem, Draft>({
        rows,
        loading,
        canManage,
        toDraft,
        blankDraft: () => ({ name: '', region: '', status: ACTIVE, school_ids: [] }),
    });
    const { draft, editing, isCreate, selected, saving, setField } = editor;

    /** Schools already in the saved directorate can only leave by being ticked in another one. */
    const lockedIds = useMemo(
        () => (isCreate || selected === null ? [] : selected.school_ids),
        [isCreate, selected],
    );
    const deactivateBlocked = draft.status === INACTIVE && draft.school_ids.length > 0;
    const canSave = editing && !saving && draft.name.trim() !== '' && !deactivateBlocked;

    const request = useRegistryRequest(RELOAD_PROPS);
    const statusUrl = (id: number, status: string): string =>
        registryStatusUrl('/organization/directorates', id, status);

    const runSave = async (): Promise<void> => {
        editor.setSaving(true);
        const payload = {
            name: draft.name.trim(),
            region: blankToNull(draft.region),
            school_ids: draft.school_ids,
        };

        try {
            if (isCreate) {
                deactivateAfterCreateRef.current = draft.status === INACTIVE;
                if (await request('post', '/organization/directorates', payload)) {
                    editor.markSaved();
                } else {
                    deactivateAfterCreateRef.current = false;
                }

                return;
            }
            if (selected === null) {
                return;
            }

            const statusChanged = draft.status !== (selected.status === 1 ? ACTIVE : INACTIVE);
            const fieldsChanged =
                payload.name !== selected.name ||
                payload.region !== (selected.region ?? null) ||
                !sameIds(draft.school_ids, selected.school_ids);

            // An inactive directorate takes no new schools: reactivate first, deactivate last.
            if (statusChanged && draft.status === ACTIVE
                && !(await request('post', statusUrl(selected.id, ACTIVE), {}))) {
                return;
            }
            if (fieldsChanged
                && !(await request('patch', `/organization/directorates/${selected.id}`, payload))) {
                return;
            }
            if (statusChanged && draft.status === INACTIVE
                && !(await request('post', statusUrl(selected.id, INACTIVE), {}))) {
                return;
            }
            editor.markSaved();
        } finally {
            editor.setSaving(false);
        }
    };

    const save = (): void => {
        if (!canSave) {
            return;
        }
        const turningOff =
            draft.status === INACTIVE && (isCreate || selected?.status === 1);
        if (turningOff) {
            setConfirmDeactivate(true);

            return;
        }
        void runSave();
    };

    // New directorate saved as inactive: deactivate it once it is selected.
    useEffect(() => {
        if (!deactivateAfterCreateRef.current || editor.mode !== 'view' || selected === null) {
            return;
        }
        deactivateAfterCreateRef.current = false;
        if (selected.status === 1) {
            void request('post', statusUrl(selected.id, INACTIVE), {});
        }
    }, [editor.mode, selected]);

    const toggleSchool = (schoolId: number): void => {
        if (!editing || lockedIds.includes(schoolId)) {
            return;
        }
        setField(
            'school_ids',
            draft.school_ids.includes(schoolId)
                ? draft.school_ids.filter((id) => id !== schoolId)
                : [...draft.school_ids, schoolId],
        );
    };

    const directorateName = (id: number): string =>
        rows.find((row) => row.id === id)?.name ?? '—';

    const columns = useMemo(
        (): DataTableColumn<DirectorateRegistryItem>[] => [
            {
                id: 'num',
                header: '#',
                cell: (row) => <span dir="ltr">{rows.indexOf(row) + 1}</span>,
            },
            { id: 'name', header: d.name, cell: (row) => row.name },
            { id: 'region', header: d.region, cell: (row) => row.region ?? '—', hideOnMobile: true },
            {
                id: 'schools',
                header: d.schoolsCount,
                cell: (row) => <span dir="ltr">{row.schools_count}</span>,
            },
            {
                id: 'status',
                header: d.status,
                cell: (row) => (row.status === 1 ? i18n.status.active : i18n.status.inactive),
            },
        ],
        [d, i18n.status.active, i18n.status.inactive, rows],
    );

    const schoolColumns: DataTableColumn<SchoolChoice>[] = [
        {
            id: 'check',
            header: '',
            cell: (school) => (
                <input
                    type="checkbox"
                    checked={draft.school_ids.includes(school.id)}
                    disabled={!editing || lockedIds.includes(school.id)}
                    onChange={() => toggleSchool(school.id)}
                    onClick={(event) => event.stopPropagation()}
                    aria-label={school.name}
                />
            ),
        },
        { id: 'name', header: i18n.schoolRegistry.name, cell: (school) => school.name },
        {
            id: 'current',
            header: d.currentDirectorate,
            cell: (school) => directorateName(school.directorate_id),
            hideOnMobile: true,
        },
    ];

    const statusOptions = [
        { value: ACTIVE, label: i18n.status.active },
        { value: INACTIVE, label: i18n.status.inactive },
    ];
    const formTitle = isCreate ? d.newDirectorate : (selected?.name ?? d.formSection);

    return (
        <RegistrySheetDialog title={d.title} className="sis-directorate-registry-sheet" onClose={onClose}>
            <SheetSection id="directorate-registry-list" title={d.listSection}>
                <DataTable
                    columns={columns}
                    rows={rows}
                    rowKey={(row) => row.id}
                    loading={loading}
                    emptyTitle={d.empty}
                    onRowClick={editing ? undefined : editor.select}
                    isRowSelected={(row) => !isCreate && row.id === editor.selectedId}
                    getRowAriaLabel={(row) => `${d.select}: ${row.name}`}
                />
            </SheetSection>

            <SheetSection id="directorate-registry-form" title={formTitle}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <RegistryTextField
                        label={d.name}
                        editing={editing}
                        value={draft.name}
                        required
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        inputRef={editor.nameInputRef}
                        onChange={(value) => setField('name', value)}
                    />
                    <RegistryTextField
                        label={d.region}
                        editing={editing}
                        value={draft.region}
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        onChange={(value) => setField('region', value)}
                    />
                    <RegistryListField
                        label={d.status}
                        editing={editing}
                        value={draft.status}
                        display={draft.status === ACTIVE ? i18n.status.active : i18n.status.inactive}
                        options={statusOptions}
                        fieldClassName="sis-student-record-form__status-field"
                        onChange={(value) => setField('status', value === INACTIVE ? INACTIVE : ACTIVE)}
                    />
                </div>
            </SheetSection>

            <SheetSection id="directorate-registry-schools" title={d.schoolsSection}>
                <DataTable
                    columns={schoolColumns}
                    rows={schools}
                    rowKey={(school) => school.id}
                    loading={loading}
                    emptyTitle={i18n.schoolRegistry.empty}
                    onRowClick={editing ? (school) => toggleSchool(school.id) : undefined}
                    isRowSelected={(school) => draft.school_ids.includes(school.id)}
                    getRowAriaLabel={(school) => school.name}
                />
                {editing ? (
                    <p className="sis-admission-sheet__empty" role="note">
                        {deactivateBlocked ? d.inactiveWithSchools : d.schoolsHint}
                    </p>
                ) : null}
            </SheetSection>

            <RegistryActions
                canManage={canManage}
                editing={editing}
                saving={saving}
                canSave={canSave}
                canEdit={selected !== null}
                loading={loading}
                onCancel={() => {
                    if (editor.cancel()) {
                        onClose();
                    }
                }}
                onAdd={editor.startCreate}
                onEdit={editor.startEdit}
                onSave={save}
            />

            <ConfirmDialog
                open={confirmDeactivate}
                title={d.confirmDeactivate}
                description={d.confirmDeactivateDescription}
                confirmPending={saving}
                tone="danger"
                onConfirm={() => {
                    setConfirmDeactivate(false);
                    void runSave();
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmDeactivate(false);
                    }
                }}
            />
        </RegistrySheetDialog>
    );
}
