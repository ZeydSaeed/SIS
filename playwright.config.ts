import { defineConfig, devices } from '@playwright/test';
import { authFile, BASE_URL, E2E_PORT, phpEnv } from './e2e/support/env';

/**
 * SIS end-to-end suite — see docs/testing/E2E-RUNBOOK.md.
 *
 * Runs only against the isolated e2e database (E2E_DB, default sis_e2e, built by `php artisan sis:e2e-seed`).
 * The server is always started here with that database: `php artisan serve` is not used because it does not pass
 * DB_* to its child process (it would silently serve the live database), and an existing server is never reused.
 */
export default defineConfig({
    testDir: './e2e',
    /* One PHP built-in server and one shared dataset: tests run one at a time, in file order. */
    fullyParallel: false,
    workers: 1,
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    timeout: 60_000,
    /* Waits are for a real state (a dialog closing after its save, a row appearing): one single-threaded PHP server
       answers a save + reload in 3–6 s, more late in a long run — 20 s, never a fixed sleep. */
    expect: { timeout: 20_000 },
    reporter: [['list'], ['html', { open: 'never' }], ['json', { outputFile: 'test-results/e2e-results.json' }]],
    use: {
        baseURL: BASE_URL,
        locale: 'ar-IQ',
        timezoneId: 'Asia/Baghdad',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        storageState: authFile('admin'),
    },

    projects: [
        { name: 'setup', testMatch: /auth\.setup\.ts/, use: { storageState: { cookies: [], origins: [] } } },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
            dependencies: ['setup'],
            testIgnore: /auth\.setup\.ts/,
        },
        /* Responsive checks: only tests tagged @responsive. */
        {
            name: 'tablet',
            use: { ...devices['Desktop Chrome'], viewport: { width: 820, height: 1180 }, hasTouch: true },
            dependencies: ['setup'],
            grep: /@responsive/,
        },
        {
            name: 'mobile',
            use: { ...devices['Pixel 7'] },
            dependencies: ['setup'],
            grep: /@responsive/,
        },
    ],

    webServer: {
        command: `php -S 127.0.0.1:${E2E_PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: 'public',
        url: `${BASE_URL}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
        /* php -S logs every request on stderr; failures surface as HTTP errors in the tests and in storage/logs. */
        stdout: 'ignore',
        stderr: 'ignore',
        env: {
            ...(phpEnv() as Record<string, string>),
            APP_URL: BASE_URL,
            /* Production behaviour: no self-service role bootstrap. */
            SECURITY_OPS_BOOTSTRAP_ENABLED: 'false',
        },
    },
});
