import { router } from '@inertiajs/react';
import { useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { EngineContext } from './engine-context';
import { VERSION, type Comparison, type EngineVersion } from './engine-types';
import { EngineField, EngineRow, EngineSelect, useEngineRequest, engineSheetClass } from './engine-ui';

const STATUS_TONE: Record<number, string> = { 1: 'info', 2: 'warning', 3: 'info', 4: 'error', 5: 'info', 6: 'warning', 7: 'warning' };

/**
 * «الإصدارات والنشر»: snapshot the working grid, send a version through the workflow approval, decide it
 * (approvers), publish it from a date, archive, restore (rollback), view it read-only, compare two.
 */
export function VersionsSheet({ ctx, comparison, onClose }: { ctx: EngineContext; comparison: Comparison | null; onClose: () => void }) {
    const i18n = t();
    const e = i18n.timetable.engine;
    const request = useEngineRequest();
    const [name, setName] = useState('');
    const [reason, setReason] = useState('');
    const [dates, setDates] = useState<Record<number, string>>({});
    const [compareA, setCompareA] = useState('');
    const [compareB, setCompareB] = useState('working');
    const [restoreId, setRestoreId] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);
    const versions = ctx.engine.versions;
    const effectiveId = ctx.engine.status.effective_version_id;
    const today = new Date().toISOString().slice(0, 10);

    const act = async (url: string, data: Record<string, string | number | null> = {}) => {
        setSaving(true);
        await request('post', url, data);
        setSaving(false);
    };

    const create = async () => {
        await act('/timetable/versions', { academic_year_id: ctx.yearId, name: name.trim(), reason: reason.trim() === '' ? null : reason.trim() });
        setName('');
        setReason('');
    };

    const compare = () => {
        router.reload({ only: ['comparison'], data: { compare_a: compareA === 'working' ? '' : compareA, compare_b: compareB === 'working' ? '' : compareB } });
    };

    const view = (v: EngineVersion) => {
        router.get('/timetable', { academic_year_id: ctx.yearId, version: v.id }, { preserveScroll: true });
    };

    const versionOptions = [{ value: 'working', label: e.compareWorking }, ...versions.map((v) => ({ value: String(v.id), label: `#${v.version_no} ${v.name}` }))];

    return (
        <RegistrySheetDialog title={e.versions} className={engineSheetClass('versions')} onClose={onClose}>
            <div className="sis-timetable-audit__list">
                {ctx.engine.status.stale ? <p className="sis-timetable-audit__item sis-timetable-audit__item--warning">{e.staleBanner}</p> : null}
                {ctx.can.publish ? (
                    <SheetSection id="timetable-version-new" title={e.newVersion}>
                        <EngineRow>
                            <EngineField label={e.versionName}>
                                <input className="sis-admission-sheet__control" value={name} maxLength={150} onChange={(ev) => setName(ev.target.value)} />
                            </EngineField>
                            <EngineField label={e.versionReason} wide>
                                <input className="sis-admission-sheet__control" value={reason} maxLength={2000} onChange={(ev) => setReason(ev.target.value)} />
                            </EngineField>
                            <Button type="button" disabled={saving || name.trim() === ''} onClick={() => void create()}>{e.save}</Button>
                        </EngineRow>
                    </SheetSection>
                ) : null}

                <SheetSection id="timetable-version-list" title={`${e.versions} (${versions.length})`}>
                    {versions.length === 0 ? <p className="sis-timetable-audit__clean">{e.noVersions}</p> : null}
                    <ul className="sis-timetable-audit__items sis-branches-field--wide">
                        {versions.map((v) => (
                            <li key={v.id} className="sis-timetable-audit__item">
                                <span className="sis-timetable-audit__text">
                                    <span className={`sis-timetable-badge sis-timetable-badge--${STATUS_TONE[v.status]}`}>{e.versionStatus[v.status]}</span>{' '}
                                    #{v.version_no} «{v.name}» · {v.entries_count} {e.entries}
                                    {v.quality?.overall != null ? ` · ${e.quality} ${v.quality.overall}%` : ''}
                                    {v.effective_from ? ` · ${e.effective}: ${v.effective_from}${v.effective_to ? ` → ${v.effective_to}` : ''}` : ''}
                                    {v.id === effectiveId ? ` · ${e.current}` : ''}
                                    {v.stale && v.status !== VERSION.archived ? ` · ${e.stale}` : ''}
                                    {v.reason ? ` · ${v.reason}` : ''}
                                </span>
                                <span>
                                    <Button type="button" size="sm" variant="outline" onClick={() => view(v)}>{e.view}</Button>{' '}
                                    {ctx.can.publish && (v.status === VERSION.draft || v.status === VERSION.rejected) ? (
                                        <Button type="button" size="sm" disabled={saving} onClick={() => void act(`/timetable/versions/${v.id}/submit`)}>{e.submit}</Button>
                                    ) : null}
                                    {ctx.can.approve && v.status === VERSION.review ? (
                                        <>
                                            <Button type="button" size="sm" disabled={saving} onClick={() => void act(`/timetable/versions/${v.id}/decide`, { decision: 'approve' })}>{e.approve}</Button>{' '}
                                            <Button type="button" size="sm" variant="outline" disabled={saving} onClick={() => void act(`/timetable/versions/${v.id}/decide`, { decision: 'reject' })}>{e.reject}</Button>
                                        </>
                                    ) : null}
                                    {ctx.can.publish && v.status === VERSION.approved ? (
                                        <>
                                            <input className="sis-admission-sheet__control" type="date" dir="ltr" aria-label={e.effectiveFrom} value={dates[v.id] ?? today} onChange={(ev) => setDates((d) => ({ ...d, [v.id]: ev.target.value }))} />{' '}
                                            <Button type="button" size="sm" disabled={saving} onClick={() => void act(`/timetable/versions/${v.id}/publish`, { effective_from: dates[v.id] ?? today })}>{e.publish}</Button>
                                        </>
                                    ) : null}
                                    {ctx.can.publish && [VERSION.draft, VERSION.rejected, VERSION.approved, VERSION.superseded].includes(v.status as 1) ? (
                                        <> <Button type="button" size="sm" variant="outline" disabled={saving} onClick={() => void act(`/timetable/versions/${v.id}/archive`)}>{e.archive}</Button></>
                                    ) : null}
                                    {ctx.can.publish ? (
                                        <> <Button type="button" size="sm" variant="outline" disabled={saving} onClick={() => setRestoreId(v.id)}>{e.restore}</Button></>
                                    ) : null}
                                </span>
                            </li>
                        ))}
                    </ul>
                </SheetSection>

                {versions.length > 0 ? (
                    <SheetSection id="timetable-version-compare" title={e.compare}>
                        <EngineRow>
                            <EngineField label="A">
                                <EngineSelect value={compareA} label="A" includeBlank onChange={setCompareA} options={versionOptions} />
                            </EngineField>
                            <EngineField label="B">
                                <EngineSelect value={compareB} label="B" onChange={setCompareB} options={versionOptions} />
                            </EngineField>
                            <Button type="button" variant="outline" disabled={compareA === '' || compareA === compareB} onClick={compare}>{e.compare}</Button>
                        </EngineRow>
                        {comparison !== null ? (
                            <p className="sis-timetable-sheet__hint">
                                {e.compareResult}: {Object.entries(comparison.counts).reduce<string>((text, [k, v]) => text.replace(`{${k}}`, String(v)), e.diffLine)}
                                {comparison.quality.a !== null || comparison.quality.b !== null ? ` · ${e.quality}: ${comparison.quality.a ?? '—'}% → ${comparison.quality.b ?? '—'}%` : ''}
                            </p>
                        ) : null}
                        <p className="sis-timetable-sheet__hint">
                            <a className="sis-ops-hub__link" href={`/timetable/export?academic_year_id=${ctx.yearId}&by=section`}>{e.exportCsv} — {i18n.timetable.bySection}</a>
                            {' · '}
                            <a className="sis-ops-hub__link" href={`/timetable/export?academic_year_id=${ctx.yearId}&by=teacher`}>{e.exportCsv} — {i18n.timetable.byTeacher}</a>
                        </p>
                    </SheetSection>
                ) : null}
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {i18n.timetable.close}
                </Button>
            </div>
            <ConfirmDialog
                open={restoreId !== null}
                title={e.restore}
                description={e.restoreConfirm}
                confirmLabel={e.restore}
                onConfirm={() => {
                    const id = restoreId;
                    setRestoreId(null);
                    if (id !== null) {
                        void act(`/timetable/versions/${id}/restore`);
                    }
                }}
                onOpenChange={(open) => (open ? null : setRestoreId(null))}
            />
        </RegistrySheetDialog>
    );
}
