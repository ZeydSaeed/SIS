/**
 * The suite's `test`: every page a test opens is watched for JavaScript errors, console errors and unexpected
 * HTTP failures from the application; the test fails with the list attached. A test that expects a refusal
 * declares it with `watch.allow(/pattern/, status)` — nothing is ignored silently.
 */
import { test as base, expect, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { authFile, BASE_URL, type Persona } from './env';

export type Problem = { kind: 'console' | 'pageerror' | 'http' | 'requestfailed'; text: string; url?: string };

export class PageWatch {
    readonly problems: Problem[] = [];

    private readonly allowed: { pattern: RegExp; status?: number }[] = [];

    allow(pattern: RegExp, status?: number): void {
        this.allowed.push({ pattern, status });
    }

    attach(page: Page): void {
        const origin = new URL(BASE_URL).origin;
        page.on('pageerror', (e) => this.problems.push({ kind: 'pageerror', text: e.message, url: page.url() }));
        page.on('console', (m) => {
            // Failed loads are reported (or allowed) by the response listener below.
            if (m.type() === 'error' && !m.text().startsWith('Failed to load resource')) {
                this.problems.push({ kind: 'console', text: m.text(), url: page.url() });
            }
        });
        page.on('response', (r) => {
            const status = r.status();
            if (status < 400 || !r.url().startsWith(origin) || this.isAllowed(r.url(), status)) {
                return;
            }
            this.problems.push({ kind: 'http', text: `${status} ${r.request().method()}`, url: r.url() });
        });
        page.on('requestfailed', (r) => {
            const failure = r.failure()?.errorText ?? '';
            // Navigations a test triggers on purpose abort the previous page's pending requests.
            if (r.url().startsWith(origin) && !failure.includes('ERR_ABORTED') && !this.isAllowed(r.url())) {
                this.problems.push({ kind: 'requestfailed', text: failure, url: r.url() });
            }
        });
    }

    private isAllowed(url: string, status?: number): boolean {
        return this.allowed.some((a) => a.pattern.test(url) && (a.status === undefined || a.status === status));
    }
}

type Fixtures = {
    watch: PageWatch;
    /** A page signed in as another persona, watched the same way. */
    pageAs: (persona: Persona) => Promise<Page>;
};

export const test = base.extend<Fixtures>({
    watch: [
        async ({ page }, use, testInfo) => {
            const watch = new PageWatch();
            watch.attach(page);
            await use(watch);
            if (watch.problems.length > 0) {
                await testInfo.attach('problems.json', { body: JSON.stringify(watch.problems, null, 2), contentType: 'application/json' });
            }
            expect(watch.problems, 'unexpected JavaScript / console / HTTP errors').toEqual([]);
        },
        { auto: true },
    ],
    pageAs: async ({ browser, watch }, use) => {
        const contexts: BrowserContext[] = [];
        await use(async (persona) => {
            const context = await newPersonaContext(browser, persona);
            contexts.push(context);
            const page = await context.newPage();
            watch.attach(page);

            return page;
        });
        await Promise.all(contexts.map((c) => c.close()));
    },
});

export async function newPersonaContext(browser: Browser, persona: Persona): Promise<BrowserContext> {
    return browser.newContext({ storageState: authFile(persona), baseURL: BASE_URL, locale: 'ar-IQ', timezoneId: 'Asia/Baghdad' });
}

/** Inertia page object of the current document (props as the server sent them). */
export async function inertiaPage<T = Record<string, unknown>>(page: Page): Promise<{ component: string; props: T; url: string }> {
    return page.evaluate(() => {
        // After a client visit Inertia keeps the current page in the history state; on a fresh load it is the
        // JSON <script data-page="app"> the server rendered.
        const state = window.history.state as { page?: unknown } | null;
        if (state?.page && typeof state.page === 'object') {
            return state.page as never;
        }

        return JSON.parse(document.querySelector('script[data-page]')?.textContent ?? '{}');
    });
}

/**
 * Props of a page fetched the way the client asks for a partial reload (optional props such as the timetable's
 * `testReport` are only computed on request). Runs in the page, so the session and the asset version are its own.
 */
export async function inertiaProps<T = Record<string, unknown>>(page: Page, url: string, only: string[]): Promise<T> {
    const current = await inertiaPage(page);

    return page.evaluate(
        async ({ url, only, component, version }) => {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html, application/xhtml+xml',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Inertia': 'true',
                    'X-Inertia-Version': version ?? '',
                    'X-Inertia-Partial-Component': component,
                    'X-Inertia-Partial-Data': only.join(','),
                },
            });
            if (!response.ok) {
                throw new Error(`partial reload ${url} → HTTP ${response.status}`);
            }

            return ((await response.json()) as { props: T }).props;
        },
        { url, only, component: current.component, version: (current as unknown as { version?: string }).version },
    );
}

export { expect };
