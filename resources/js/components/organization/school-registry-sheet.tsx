import { useMemo, useState } from 'react';
import {
    blankToNull,
    RegistryActions,
    RegistryListField,
    RegistrySheetDialog,
    RegistryTextField,
    statusUrl,
    useRegistryEditor,
    useRegistryRequest,
} from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { t } from '@/i18n';

export type SchoolRegistryItem = {
    id: number;
    directorate_id: number;
    directorate_name: string | null;
    code: string;
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    status: number;
};

export type SchoolRegistry = {
    schools: SchoolRegistryItem[];
    directorates: { id: number; name: string }[];
};

type Draft = {
    name: string;
    directorate_id: string;
    phone: string;
    email: string;
    address: string;
    status: '1' | '2';
};

const ACTIVE = '1';
const INACTIVE = '2';

/** Props reloaded after a save — the school switcher (schoolContext) lists new schools too. */
const RELOAD_PROPS = ['schoolRegistry', 'directorateRegistry', 'schoolContext', 'flash'];

function toDraft(school: SchoolRegistryItem): Draft {
    return {
        name: school.name,
        directorate_id: String(school.directorate_id),
        phone: school.phone ?? '',
        email: school.email ?? '',
        address: school.address ?? '',
        status: school.status === 1 ? ACTIVE : INACTIVE,
    };
}

type Props = {
    registry: SchoolRegistry | null;
    canManage: boolean;
    /** Open on this school (e.g. the school chosen on the admission page). */
    initialSchoolId?: number | null;
    initialMode?: 'view' | 'edit';
    onClose: () => void;
};

/** School registry — the user's schools + add / edit form in one sheet. */
export function SchoolRegistrySheetDialog({
    registry,
    canManage,
    initialSchoolId = null,
    initialMode = 'view',
    onClose,
}: Props) {
    const i18n = t();
    const s = i18n.schoolRegistry;
    const request = useRegistryRequest(RELOAD_PROPS);
    const [confirmDeactivate, setConfirmDeactivate] = useState(false);
    const schools = useMemo(() => registry?.schools ?? [], [registry]);
    const directorates = useMemo(() => registry?.directorates ?? [], [registry]);
    const loading = registry === null;

    const editor = useRegistryEditor<SchoolRegistryItem, Draft>({
        rows: schools,
        loading,
        canManage,
        toDraft,
        blankDraft: () => ({
            name: '',
            directorate_id: directorates.length > 0 ? String(directorates[0].id) : '',
            phone: '',
            email: '',
            address: '',
            status: ACTIVE,
        }),
        initialId: initialSchoolId,
        initialMode,
    });
    const { draft, editing, isCreate, selected, saving, setField } = editor;

    const canSave =
        editing && !saving && draft.name.trim() !== '' && draft.directorate_id !== '';

    const runSave = async (): Promise<void> => {
        editor.setSaving(true);
        const payload = {
            name: draft.name.trim(),
            directorate_id: Number(draft.directorate_id),
            phone: blankToNull(draft.phone),
            email: blankToNull(draft.email),
            address: blankToNull(draft.address),
        };

        try {
            if (isCreate) {
                if (await request('post', '/organization/schools', payload)) {
                    editor.markSaved();
                }

                return;
            }
            if (selected === null) {
                return;
            }

            const statusChanged = draft.status !== (selected.status === 1 ? ACTIVE : INACTIVE);
            const fieldsChanged =
                payload.name !== selected.name ||
                payload.directorate_id !== selected.directorate_id ||
                payload.phone !== (selected.phone ?? null) ||
                payload.email !== (selected.email ?? null) ||
                payload.address !== (selected.address ?? null);
            const statusPath = statusUrl('/organization/schools', selected.id, draft.status);

            // Reactivate first, deactivate last — fields are saved in between.
            if (statusChanged && draft.status === ACTIVE && !(await request('post', statusPath))) {
                return;
            }
            if (fieldsChanged
                && !(await request('patch', `/organization/schools/${selected.id}`, payload))) {
                return;
            }
            if (statusChanged && draft.status === INACTIVE && !(await request('post', statusPath))) {
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
        if (!isCreate && draft.status === INACTIVE && selected?.status === 1) {
            setConfirmDeactivate(true);

            return;
        }
        void runSave();
    };

    const directorateOptions = useMemo(
        () => directorates.map((item) => ({ value: String(item.id), label: item.name })),
        [directorates],
    );
    const directorateDisplay =
        directorates.find((item) => String(item.id) === draft.directorate_id)?.name ??
        selected?.directorate_name ??
        '—';

    const columns = useMemo(
        (): DataTableColumn<SchoolRegistryItem>[] => [
            {
                id: 'num',
                header: '#',
                cell: (row) => <span dir="ltr">{schools.indexOf(row) + 1}</span>,
            },
            { id: 'name', header: s.name, cell: (row) => row.name },
            {
                id: 'directorate',
                header: s.directorate,
                cell: (row) => row.directorate_name ?? '—',
                hideOnMobile: true,
            },
            {
                id: 'phone',
                header: s.phone,
                cell: (row) => <span dir="ltr">{row.phone ?? '—'}</span>,
                hideOnMobile: true,
            },
            {
                id: 'status',
                header: s.status,
                cell: (row) => (row.status === 1 ? i18n.status.active : i18n.status.inactive),
            },
        ],
        [i18n.status.active, i18n.status.inactive, s, schools],
    );

    const formTitle = isCreate ? s.newSchool : (selected?.name ?? s.formSection);

    return (
        <RegistrySheetDialog title={s.title} className="sis-school-registry-sheet" onClose={onClose}>
            <SheetSection id="school-registry-list" title={s.listSection}>
                <DataTable
                    columns={columns}
                    rows={schools}
                    rowKey={(row) => row.id}
                    loading={loading}
                    emptyTitle={s.empty}
                    onRowClick={editing ? undefined : editor.select}
                    isRowSelected={(row) => !isCreate && row.id === editor.selectedId}
                    getRowAriaLabel={(row) => `${s.select}: ${row.name}`}
                />
            </SheetSection>

            <SheetSection id="school-registry-form" title={formTitle}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <RegistryTextField
                        label={s.name}
                        editing={editing}
                        value={draft.name}
                        required
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        inputRef={editor.nameInputRef}
                        onChange={(value) => setField('name', value)}
                    />
                    <RegistryListField
                        label={s.directorate}
                        editing={editing}
                        value={draft.directorate_id}
                        display={directorateDisplay}
                        options={directorateOptions}
                        required
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        onChange={(value) => setField('directorate_id', value)}
                    />
                    <RegistryListField
                        label={s.status}
                        editing={editing && !isCreate}
                        value={draft.status}
                        display={draft.status === ACTIVE ? i18n.status.active : i18n.status.inactive}
                        options={[
                            { value: ACTIVE, label: i18n.status.active },
                            { value: INACTIVE, label: i18n.status.inactive },
                        ]}
                        fieldClassName="sis-student-record-form__status-field"
                        onChange={(value) => setField('status', value === INACTIVE ? INACTIVE : ACTIVE)}
                    />
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <RegistryTextField
                        label={s.phone}
                        editing={editing}
                        value={draft.phone}
                        dir="ltr"
                        type="tel"
                        fieldClassName="sis-enrollment-record-sheet__field--narrow"
                        onChange={(value) => setField('phone', value)}
                    />
                    <RegistryTextField
                        label={s.email}
                        editing={editing}
                        value={draft.email}
                        dir="ltr"
                        type="email"
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        onChange={(value) => setField('email', value)}
                    />
                    <RegistryTextField
                        label={s.address}
                        editing={editing}
                        value={draft.address}
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        onChange={(value) => setField('address', value)}
                    />
                </div>
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
                title={s.confirmDeactivate}
                description={s.confirmDeactivateDescription}
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
