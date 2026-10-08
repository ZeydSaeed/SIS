import { useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { EngineContext } from './engine-context';
import type { EnginePlaceRoom, EnginePlaceWorkshop } from './engine-types';
import { EngineField, EngineNumber, EngineRow, EngineSelect, toInt, useEngineRequest, engineSheetClass } from './engine-ui';

type RoomForm = { branch: string; code: string; name: string; capacity: string; type: string };
type WorkshopForm = { code: string; name: string; capacity: string; safety: string; room: string };

const IN_SERVICE = 1;

/**
 * «الأماكن»: the rooms (classrooms and practical rooms) and workshops lessons are held in — add, edit, take out of
 * service and back. A place that a lesson, an activity or a workshop still points at cannot leave service.
 */
export function PlacesSheet({ ctx, onClose }: { ctx: EngineContext; onClose: () => void }) {
    const i18n = t();
    const e = i18n.timetable.engine;
    const request = useEngineRequest();
    const places = ctx.engine.places;
    const [saving, setSaving] = useState(false);
    const [room, setRoom] = useState<{ id: number | null; form: RoomForm } | null>(null);
    const [workshop, setWorkshop] = useState<{ id: number | null; form: WorkshopForm } | null>(null);

    const roomOf = (id: number | null) => places.rooms.find((r) => r.id === id);
    const blankRoom = (): RoomForm => ({ branch: String(ctx.branches[0]?.id ?? ''), code: '', name: '', capacity: '40', type: '1' });
    const blankWorkshop = (): WorkshopForm => ({ code: '', name: '', capacity: '20', safety: '16', room: '' });
    const typeName = (n: number) => e.roomTypeNames[n] ?? String(n);

    const saveRoom = async () => {
        if (room === null) {
            return;
        }
        setSaving(true);
        const f = room.form;
        const ok = await request(room.id === null ? 'post' : 'patch', room.id === null ? '/timetable/places/rooms' : `/timetable/places/rooms/${room.id}`, {
            ...(room.id === null ? { branch_id: Number(f.branch), code: f.code.trim() } : {}),
            name: f.name.trim(),
            capacity: toInt(f.capacity),
            room_type: Number(f.type),
        });
        setSaving(false);
        if (ok) {
            setRoom(null);
        }
    };

    const saveWorkshop = async () => {
        if (workshop === null) {
            return;
        }
        setSaving(true);
        const f = workshop.form;
        const ok = await request(workshop.id === null ? 'post' : 'patch', workshop.id === null ? '/timetable/places/workshops' : `/timetable/places/workshops/${workshop.id}`, {
            ...(workshop.id === null ? { code: f.code.trim() } : {}),
            name: f.name.trim(),
            capacity: Number(f.capacity),
            safety_capacity: Number(f.safety),
            room_id: toInt(f.room),
        });
        setSaving(false);
        if (ok) {
            setWorkshop(null);
        }
    };

    const toggle = (kind: 'room' | 'workshop', id: number, inService: boolean) =>
        void request('post', '/timetable/places/status', { kind, id, active: inService ? 0 : 1 });

    const describeRoom = (r: EnginePlaceRoom) =>
        `${r.code} — ${r.name} · ${r.branch_name} · ${typeName(r.room_type)}${r.capacity ? ` · ${e.capacity}: ${r.capacity}` : ''}${r.used > 0 ? ` · ${e.placeUsed.replace('{n}', String(r.used))}` : ''}${r.status !== IN_SERVICE ? ` · ${e.placeOut}` : ''}`;
    const describeWorkshop = (w: EnginePlaceWorkshop) =>
        `${w.code} — ${w.name} · ${e.capacity}: ${w.capacity} · ${e.safetyCapacity}: ${w.safety_capacity}${w.room_id !== null ? ` · ${e.room}: ${roomOf(w.room_id)?.code ?? w.room_id}` : ''}${w.used > 0 ? ` · ${e.placeUsed.replace('{n}', String(w.used))}` : ''}${w.status !== IN_SERVICE ? ` · ${e.placeOut}` : ''}`;

    return (
        <RegistrySheetDialog title={e.places} className={engineSheetClass('places')} onClose={onClose}>
            <div className="sis-timetable-audit__list">
                <SheetSection id="timetable-places-rooms" title={`${e.placesRooms} (${places.rooms.length})`}>
                    {ctx.can.manage ? (
                        <div className="sis-timetable-audit__bar">
                            <Button type="button" size="sm" variant="outline" onClick={() => setRoom({ id: null, form: blankRoom() })}>
                                {e.newRoom}
                            </Button>
                        </div>
                    ) : null}
                    {places.rooms.length === 0 ? <p className="sis-timetable-audit__clean">{e.noPlaces}</p> : null}
                    <ul className="sis-timetable-audit__items sis-branches-field--wide">
                        {places.rooms.map((r) => (
                            <li key={r.id} className="sis-timetable-audit__item">
                                <span className="sis-timetable-audit__text">{describeRoom(r)}</span>
                                {ctx.can.manage ? (
                                    <span>
                                        <Button type="button" size="sm" variant="outline" onClick={() => setRoom({ id: r.id, form: { branch: String(r.branch_id), code: r.code, name: r.name, capacity: r.capacity === null ? '' : String(r.capacity), type: String(r.room_type) } })}>
                                            {e.edit}
                                        </Button>{' '}
                                        <Button type="button" size="sm" variant="outline" disabled={r.status === IN_SERVICE && r.used > 0} title={r.status === IN_SERVICE && r.used > 0 ? e.placeInUseHint : undefined} onClick={() => toggle('room', r.id, r.status === IN_SERVICE)}>
                                            {r.status === IN_SERVICE ? e.placeDisable : e.placeEnable}
                                        </Button>
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </SheetSection>
                {room !== null && ctx.can.manage ? (
                    <SheetSection id="timetable-places-room-form" title={room.id === null ? e.newRoom : e.editRoom}>
                        <EngineRow two>
                            <EngineField label={e.placeBranch}>
                                <EngineSelect value={room.form.branch} label={e.placeBranch} onChange={(v) => setRoom({ ...room, form: { ...room.form, branch: v } })} options={ctx.branches.map((b) => ({ value: String(b.id), label: b.name }))} />
                            </EngineField>
                            <EngineField label={e.placeCode}>
                                <input className="sis-admission-sheet__control" dir="ltr" value={room.form.code} maxLength={30} disabled={room.id !== null} onChange={(ev) => setRoom({ ...room, form: { ...room.form, code: ev.target.value } })} />
                            </EngineField>
                            <EngineField label={e.placeName}>
                                <input className="sis-admission-sheet__control" value={room.form.name} maxLength={150} onChange={(ev) => setRoom({ ...room, form: { ...room.form, name: ev.target.value } })} />
                            </EngineField>
                            <EngineField label={e.roomType}>
                                <EngineSelect value={room.form.type} label={e.roomType} onChange={(v) => setRoom({ ...room, form: { ...room.form, type: v } })} options={[1, 2].map((n) => ({ value: String(n), label: typeName(n) }))} />
                            </EngineField>
                            <EngineField label={e.capacity}>
                                <EngineNumber value={room.form.capacity} onChange={(v) => setRoom({ ...room, form: { ...room.form, capacity: v } })} min={1} max={500} label={e.capacity} />
                            </EngineField>
                        </EngineRow>
                        <div className="sis-admission-sheet__actions">
                            <Button type="button" disabled={saving || room.form.name.trim() === '' || (room.id === null && (room.form.code.trim() === '' || room.form.branch === ''))} onClick={() => void saveRoom()}>
                                {saving ? i18n.common.saving : e.save}
                            </Button>
                            <Button type="button" variant="outline" onClick={() => setRoom(null)}>
                                {i18n.timetable.cancelEdit}
                            </Button>
                        </div>
                    </SheetSection>
                ) : null}
                <SheetSection id="timetable-places-workshops" title={`${e.placesWorkshops} (${places.workshops.length})`}>
                    {ctx.can.manage ? (
                        <div className="sis-timetable-audit__bar">
                            <Button type="button" size="sm" variant="outline" onClick={() => setWorkshop({ id: null, form: blankWorkshop() })}>
                                {e.newWorkshop}
                            </Button>
                        </div>
                    ) : null}
                    {places.workshops.length === 0 ? <p className="sis-timetable-audit__clean">{e.noPlaces}</p> : null}
                    <ul className="sis-timetable-audit__items sis-branches-field--wide">
                        {places.workshops.map((w) => (
                            <li key={w.id} className="sis-timetable-audit__item">
                                <span className="sis-timetable-audit__text">{describeWorkshop(w)}</span>
                                {ctx.can.manage ? (
                                    <span>
                                        <Button type="button" size="sm" variant="outline" onClick={() => setWorkshop({ id: w.id, form: { code: w.code, name: w.name, capacity: String(w.capacity), safety: String(w.safety_capacity), room: w.room_id === null ? '' : String(w.room_id) } })}>
                                            {e.edit}
                                        </Button>{' '}
                                        <Button type="button" size="sm" variant="outline" disabled={w.status === IN_SERVICE && w.used > 0} title={w.status === IN_SERVICE && w.used > 0 ? e.placeInUseHint : undefined} onClick={() => toggle('workshop', w.id, w.status === IN_SERVICE)}>
                                            {w.status === IN_SERVICE ? e.placeDisable : e.placeEnable}
                                        </Button>
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </SheetSection>
                {workshop !== null && ctx.can.manage ? (
                    <SheetSection id="timetable-places-workshop-form" title={workshop.id === null ? e.newWorkshop : e.editWorkshop}>
                        <EngineRow two>
                            <EngineField label={e.placeCode}>
                                <input className="sis-admission-sheet__control" dir="ltr" value={workshop.form.code} maxLength={30} disabled={workshop.id !== null} onChange={(ev) => setWorkshop({ ...workshop, form: { ...workshop.form, code: ev.target.value } })} />
                            </EngineField>
                            <EngineField label={e.placeName}>
                                <input className="sis-admission-sheet__control" value={workshop.form.name} maxLength={150} onChange={(ev) => setWorkshop({ ...workshop, form: { ...workshop.form, name: ev.target.value } })} />
                            </EngineField>
                            <EngineField label={e.capacity}>
                                <EngineNumber value={workshop.form.capacity} onChange={(v) => setWorkshop({ ...workshop, form: { ...workshop.form, capacity: v } })} min={1} max={500} label={e.capacity} />
                            </EngineField>
                            <EngineField label={e.safetyCapacity}>
                                <EngineNumber value={workshop.form.safety} onChange={(v) => setWorkshop({ ...workshop, form: { ...workshop.form, safety: v } })} min={1} max={500} label={e.safetyCapacity} />
                            </EngineField>
                            <EngineField label={e.room}>
                                <EngineSelect value={workshop.form.room} label={e.room} includeBlank onChange={(v) => setWorkshop({ ...workshop, form: { ...workshop.form, room: v } })} options={places.rooms.filter((r) => r.status === IN_SERVICE).map((r) => ({ value: String(r.id), label: `${r.code} — ${r.name}` }))} />
                            </EngineField>
                        </EngineRow>
                        <p className="sis-timetable-sheet__hint">{e.safetyHint}</p>
                        <div className="sis-admission-sheet__actions">
                            <Button type="button" disabled={saving || workshop.form.name.trim() === '' || (workshop.id === null && workshop.form.code.trim() === '')} onClick={() => void saveWorkshop()}>
                                {saving ? i18n.common.saving : e.save}
                            </Button>
                            <Button type="button" variant="outline" onClick={() => setWorkshop(null)}>
                                {i18n.timetable.cancelEdit}
                            </Button>
                        </div>
                    </SheetSection>
                ) : null}
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {i18n.timetable.close}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}
