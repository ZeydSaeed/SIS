import type { Page } from '@playwright/test';

/**
 * Click a command of the «تحرير» ribbon of the exams / grades / results pages, opening the tab first only when the
 * command is not already shown (clicking an open tab may fold it).
 */
export async function command(page: Page, name: string | RegExp): Promise<void> {
    const button = page.getByRole('button', { name, exact: typeof name === 'string' }).first();
    if (!(await button.isVisible())) {
        await page.getByRole('tab', { name: 'تحرير', exact: true }).or(page.getByRole('button', { name: 'تحرير', exact: true })).first().click();
    }
    await button.click();
}

/** Open the «تحرير» ribbon tab (when it is not open) so its commands can be checked. */
export async function editTab(page: Page): Promise<void> {
    const tab = page.getByRole('tab', { name: 'تحرير', exact: true }).or(page.getByRole('button', { name: 'تحرير', exact: true })).first();
    if ((await tab.getAttribute('aria-selected')) !== 'true' && (await tab.getAttribute('aria-pressed')) !== 'true') {
        await tab.click();
    }
}
