/**
 * Environment of the e2e suite. The suite only ever talks to an isolated database whose name starts with
 * «sis_e2e» (built by `php artisan sis:e2e-seed`); the persona password comes from SIS_E2E_PASSWORD and is
 * never written to the repository.
 */
import path from 'node:path';

export const E2E_DB = process.env.E2E_DB ?? 'sis_e2e';
export const E2E_PORT = Number(process.env.E2E_PORT ?? 8123);
export const BASE_URL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${E2E_PORT}`;

if (!/^sis_e2e[a-z0-9_]*$/.test(E2E_DB)) {
    throw new Error(`E2E_DB «${E2E_DB}» is not an e2e database (the name must start with sis_e2e).`);
}

export const PERSONAS = {
    admin: 'e2e-admin@sis.test',
    school2: 'e2e-school2@sis.test',
    viewer: 'e2e-viewer@sis.test',
    teacher: 'e2e-teacher@sis.test',
    noroles: 'e2e-noroles@sis.test',
} as const;

export type Persona = keyof typeof PERSONAS;

/**
 * Tag of this run (base-36 start time): codes and names created through the UI carry it, so a record can be traced
 * to its run and a second run against the same dataset never collides with the first.
 */
export const RUN = (process.env.E2E_RUN ??= Date.now().toString(36).toUpperCase());

export function personaPassword(): string {
    const password = process.env.SIS_E2E_PASSWORD ?? '';
    if (password.length < 12) {
        throw new Error('SIS_E2E_PASSWORD (12+ characters, the one used by sis:e2e-seed) is required.');
    }

    return password;
}

export function authFile(persona: Persona): string {
    return path.join('playwright', '.auth', `${persona}.json`);
}

/** Environment for PHP processes (server, db helper) — the live database is never reachable from here. */
export function phpEnv(): NodeJS.ProcessEnv {
    return {
        ...process.env,
        DB_CONNECTION: 'pgsql',
        DB_DATABASE: E2E_DB,
        SIS_DESTRUCTIVE_ALLOWED_DATABASES: E2E_DB,
    };
}
