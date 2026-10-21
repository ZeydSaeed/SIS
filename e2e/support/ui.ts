import { expect, type Locator, type Page } from '@playwright/test';

/** Click a ribbon tab (ملف · الصفحة الرئيسية · تحرير · اضافة · …) and then one of its commands. */
export async function ribbon(page: Page, tab: string, command: string | RegExp): Promise<void> {
    await page.getByRole('tab', { name: tab, exact: true }).or(page.getByRole('button', { name: tab, exact: true })).first().click();
    await page.getByRole('button', { name: command, exact: typeof command === 'string' }).first().click();
}

/**
 * The open form window. The page's message window (MessageDialog) is a dialog too, so a form is told apart by its
 * title or, without one, by holding a field.
 */
export function openDialog(page: Page, title?: string | RegExp): Locator {
    const dialogs = page.locator('[role=dialog]:visible');

    return (title ? dialogs.filter({ hasText: title }) : dialogs.filter({ has: page.locator('input, textarea, [role=combobox]') })).last();
}

/** A field of a form by its visible label; «*» of required fields is ignored. */
export function field(scope: Locator | Page, label: string | RegExp): Locator {
    const name = typeof label === 'string' ? new RegExp(`^\\s*${label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\*?\\s*$`) : label;

    // Only form controls: a section may carry the same name as one of its fields (e.g. «الموقع»).
    return scope.getByLabel(name).and(scope.locator('input, textarea, select'));
}

/** Pick an option of a select / combobox field. */
export async function choose(page: Page, scope: Locator, label: string, option: string | RegExp): Promise<void> {
    await scope.getByRole('combobox', { name: new RegExp(label) }).first().click();
    await page.getByRole('option', { name: option, exact: typeof option === 'string' }).first().click();
}

/** The message window (success: dialog · error / warning: alertdialog) or a toast showing the text. */
export function notice(page: Page, text: string | RegExp): Locator {
    return page.locator('[role=dialog], [role=alertdialog], [data-sonner-toast], [role=status]').filter({ hasText: text }).last();
}

export async function expectNotice(page: Page, text: string | RegExp): Promise<void> {
    await expect(notice(page, text)).toBeVisible();
}

/** Close the message window showing the text (it is not modal, but it covers part of the page). */
export async function closeNotice(page: Page, text: string | RegExp): Promise<void> {
    const box = notice(page, text);
    await box.getByRole('button', { name: 'إغلاق', exact: true }).last().click();
    await expect(box).toBeHidden();
}
