/**
 * «الغرف الدراسية» (rooms, labs, workshops): create a room with every field of the sheet and read it back from the
 * database, edit it, refuse a duplicate code with its message, take it out of service behind a confirmation — and
 * every limit of the form (capacity, floor, lengths, colour) at and just past its boundary.
 */
import { dbQuery, dbValue } from '../support/db';
import { RUN } from '../support/env';
import { attempt } from '../support/http';
import { expect, test } from '../support/test';
import { choose, closeNotice, expectNotice, field, notice, openDialog, ribbon } from '../support/ui';

test.describe.configure({ mode: 'serial' });

const code = `R-${RUN}`.slice(0, 20);
const room = () => dbQuery<Record<string, unknown>>(
    `SELECT r.code, r.name, r.room_number, r.capacity, r.building, r.floor, r.location, r.supports_practical, r.equipment, r.suitable_for,
            r.notes, r.abbreviation, r.color_hue, r.status, b.name AS branch, t.code AS room_type, d.name AS department
       FROM organization.rooms r JOIN organization.branches b ON b.id = r.branch_id
       LEFT JOIN organization.room_types t ON t.id = r.room_type_id LEFT JOIN organization.departments d ON d.id = r.department_id
      WHERE r.code = ?`, [code])[0];

test('create a workshop with every field of the sheet', async ({ page }) => {
    await page.goto('/organization/rooms');
    await ribbon(page, 'تحرير', 'إضافة غرفة');
    const sheet = openDialog(page, 'إضافة غرفة');
    await choose(page, sheet, 'الفرع', /^الصناعي/);
    await field(sheet, 'الرمز').fill(code);
    await field(sheet, 'رقم الغرفة').fill('W-12');
    await field(sheet, 'اسم الغرفة').fill(`ورشة الكهرباء ${RUN}`);
    await choose(page, sheet, 'نوع الغرفة', /^ورشة صناعية/);
    await field(sheet, 'السعة (عدد الطلاب الأقصى)').fill('24');
    await sheet.getByRole('checkbox', { name: /تدعم العملي/ }).check();
    await field(sheet, 'المبنى').fill('المبنى الفني');
    await field(sheet, 'الطابق').fill('-1');
    await field(sheet, 'الموقع').fill('الجناح الشرقي');
    await choose(page, sheet, 'القسم / الاختصاص', /^كهرباء/);
    await field(sheet, 'التجهيزات').fill('لوحات تدريب، أجهزة قياس، عدد يدوية');
    await field(sheet, 'المواد / الأنشطة المناسبة').fill('التدريب العملي — كهرباء');
    await field(sheet, 'الملاحظات').fill('تحتاج تهوية إضافية صيفاً');
    await field(sheet, /^\s*الاختصار/).fill('وك');
    await sheet.getByRole('radio', { name: 'اللون 130' }).check();
    await sheet.getByRole('button', { name: 'حفظ', exact: true }).click();

    await expect(sheet).toBeHidden();
    await expectNotice(page, /تم/);
    await closeNotice(page, /تم/);
    expect(room()).toEqual({
        code, name: `ورشة الكهرباء ${RUN}`, room_number: 'W-12', capacity: 24, building: 'المبنى الفني', floor: -1, location: 'الجناح الشرقي',
        supports_practical: true, equipment: 'لوحات تدريب، أجهزة قياس، عدد يدوية', suitable_for: 'التدريب العملي — كهرباء',
        notes: 'تحتاج تهوية إضافية صيفاً', abbreviation: 'وك', color_hue: 130, status: 1, branch: 'الصناعي', room_type: 'INDUSTRIAL_WORKSHOP', department: 'كهرباء',
    });

    // Found by its code (the «الرمز» column shows the timetable abbreviation when one is set — see the coverage notes).
    await page.getByRole('searchbox', { name: /بحث في الغرف/ }).fill(code);
    await expect(page.getByRole('row').filter({ hasText: `ورشة الكهرباء ${RUN}` })).toHaveCount(1);
});

test('refused: a code already used — message shown, nothing written', async ({ page }) => {
    const before = dbValue('SELECT count(*) FROM organization.rooms');
    await page.goto('/organization/rooms');
    await ribbon(page, 'تحرير', 'إضافة غرفة');
    const sheet = openDialog(page, 'إضافة غرفة');
    await choose(page, sheet, 'الفرع', /^الصناعي/);
    await field(sheet, 'الرمز').fill(code);
    await field(sheet, 'اسم الغرفة').fill('غرفة مكررة');
    await sheet.getByRole('button', { name: 'حفظ', exact: true }).click();

    await expect(notice(page, /مستخدم|موجود|مسجل/)).toBeVisible();
    await expect(sheet).toBeVisible();
    expect(dbValue('SELECT count(*) FROM organization.rooms')).toBe(before);
});

test('edit the capacity', async ({ page }) => {
    await page.goto('/organization/rooms');
    await page.getByRole('searchbox', { name: /بحث في الغرف/ }).fill(code);
    await page.getByRole('checkbox', { name: `تحديد الغرفة: ورشة الكهرباء ${RUN}`, exact: true }).check();
    await ribbon(page, 'تحرير', 'تعديل');
    const sheet = openDialog(page, /تعديل/);
    await field(sheet, 'السعة (عدد الطلاب الأقصى)').fill('30');
    await sheet.getByRole('button', { name: 'حفظ', exact: true }).click();
    await expect(sheet).toBeHidden();
    await expect.poll(() => room()?.capacity).toBe(30);
});

test('take the room out of service only after confirming', async ({ page }) => {
    await page.goto('/organization/rooms');
    await page.getByRole('searchbox', { name: /بحث في الغرف/ }).fill(code);
    await page.getByRole('checkbox', { name: `تحديد الغرفة: ورشة الكهرباء ${RUN}`, exact: true }).check();
    await ribbon(page, 'تحرير', 'إخراج من الخدمة');
    const confirm = openDialog(page, /إخراج|إيقاف/);
    await expect(confirm).toBeVisible();
    expect(room()?.status).toBe(1);
    await confirm.getByRole('button', { name: /إخراج|إيقاف|تأكيد/ }).last().click();
    await expect.poll(() => room()?.status).toBe(2); // RoomCatalogueRepositoryInterface::INACTIVE
});

test('every limit of the room form at and past its boundary', async ({ page }) => {
    const branch = dbValue<number>("SELECT id FROM organization.branches WHERE school_id = 1 AND name = 'الصناعي'");
    let n = 0;
    const base = () => ({ branch_id: branch, code: `B${RUN}${++n}`.slice(-20), name: `حدود ${RUN} ${n}`, supports_practical: false });
    const cases: [string, Record<string, unknown>, boolean][] = [
        ['capacity 1', { capacity: 1 }, true], ['capacity 500', { capacity: 500 }, true],
        ['capacity 0', { capacity: 0 }, false], ['capacity 501', { capacity: 501 }, false], ['capacity -3', { capacity: -3 }, false], ['capacity «ثلاثون»', { capacity: 'ثلاثون' }, false],
        ['floor -5', { floor: -5 }, true], ['floor 100', { floor: 100 }, true], ['floor -6', { floor: -6 }, false], ['floor 101', { floor: 101 }, false],
        ['colour 0', { color_hue: 0 }, true], ['colour 359', { color_hue: 359 }, true], ['colour 360', { color_hue: 360 }, false],
        ['name of 100 characters', { name: 'ن'.repeat(100) }, true], ['name of 101 characters', { name: 'ن'.repeat(101) }, false], ['blank name', { name: '   ' }, false],
        ['code of 21 characters', { code: 'C'.repeat(21) }, false], ['blank code', { code: '' }, false],
        ['equipment of 2000 characters', { equipment: 'ت'.repeat(2000) }, true], ['equipment of 2001 characters', { equipment: 'ت'.repeat(2001) }, false],
        ['abbreviation of 21 characters', { abbreviation: 'ا'.repeat(21) }, false],
        ['unknown room type', { room_type_id: 999999 }, false], ['unknown department', { department_id: 999999 }, false],
        ['branch of another school', { branch_id: dbValue<number>('SELECT id FROM organization.branches WHERE school_id = 2 ORDER BY id LIMIT 1') }, false],
        ['school_id smuggled in', { school_id: 2 }, false],
    ];
    const outcomes: string[] = [];
    for (const [label, change, accepted] of cases) {
        const before = Number(dbValue('SELECT count(*) FROM organization.rooms'));
        const result = await attempt(page, 'POST', '/organization/rooms', { ...base(), ...change });
        expect(result.status, `${label}: no server error`).toBeLessThan(500);
        const after = Number(dbValue('SELECT count(*) FROM organization.rooms'));
        outcomes.push(`${label}: ${result.ok ? 'accepted' : 'refused ' + Object.values(result.errors).join(', ')}`);
        expect(result.ok, `${label} → ${JSON.stringify(result.errors)}`).toBe(accepted);
        expect(after - before, `${label}: rows written`).toBe(accepted ? 1 : 0);
    }
    test.info().annotations.push({ type: 'boundaries', description: outcomes.join(' · ') });
});
