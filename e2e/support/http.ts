import type { APIResponse, Page } from '@playwright/test';

let sequence = 0;

/**
 * A same-origin write from the signed-in page's session (cookies + CSRF), the way the application's own client sends
 * it. The idempotency key is traceable: the caller's label plus a per-run sequence.
 */
export async function send(page: Page, method: 'POST' | 'PUT' | 'PATCH' | 'DELETE', url: string, data: Record<string, unknown> = {}, key?: string): Promise<APIResponse> {
    if (page.url() === 'about:blank') {
        await page.goto('/');
    }
    const xsrf = decodeURIComponent((await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')?.value ?? '');
    sequence += 1;

    return page.request.fetch(url, {
        method,
        data,
        maxRedirects: 0,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf,
            'X-Idempotency-Key': key ?? `e2e:${process.pid}:${Date.now()}:${sequence}`,
            // back() after a refusal returns here — not to whatever page the session saw last.
            ...(page.url().startsWith('http') ? { Referer: page.url() } : {}),
        },
    });
}

/**
 * A write and its outcome as the user would see it: field errors from a 422, or — the web pages' convention for a
 * refusal by the business rules — the error codes flashed into the session by a redirect back (read from the next
 * page the browser opens). `ok` is true only for a 2xx / redirect without errors.
 */
export async function attempt(
    page: Page,
    method: 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    url: string,
    data: Record<string, unknown> = {},
): Promise<{ status: number; ok: boolean; errors: Record<string, string> }> {
    const response = await send(page, method, url, data);
    const status = response.status();
    let errors: Record<string, string> = {};
    if (status >= 400 && status < 500 && (response.headers()['content-type'] ?? '').includes('json')) {
        // Validation: {errors: {field: [..]}} · a domain refusal (bootstrap/app.php): {message, error_code}.
        const body = (await response.json()) as { errors?: Record<string, string[] | string>; error_code?: string; message?: string };
        errors = Object.fromEntries(Object.entries(body.errors ?? {}).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
        if (body.error_code) {
            errors.error_code = body.error_code;
        }
        if (Object.keys(errors).length === 0) {
            errors.message = body.message ?? `HTTP ${status}`;
        }
    } else if (status === 302 || status === 303) {
        await page.goto(response.headers()['location'] ?? '/');
        // Both refusal conventions of the web pages: withErrors() → props.errors, a domain refusal → props.flash.error.
        errors = await page.evaluate(() => {
            type Props = { errors?: Record<string, string>; flash?: { error?: string | null } };
            const state = window.history.state as { page?: { props?: Props } } | null;
            const props = state?.page?.props ?? (JSON.parse(document.querySelector('script[data-page]')?.textContent ?? '{}') as { props?: Props }).props ?? {};
            const found = { ...(props.errors ?? {}) };
            if (props.flash?.error) {
                found.flash_error = props.flash.error;
            }

            return found;
        });
    }

    return { status, ok: status < 400 && Object.keys(errors).length === 0, errors };
}
