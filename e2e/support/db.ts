import { execFileSync } from 'node:child_process';
import { phpEnv } from './env';

/** One read-only SELECT against the e2e database (see db-query.php). */
export function dbQuery<T = Record<string, unknown>>(sql: string, bindings: unknown[] = []): T[] {
    const out = execFileSync('php', ['e2e/support/db-query.php'], {
        input: JSON.stringify({ sql, bindings }),
        env: phpEnv(),
        encoding: 'utf8',
        maxBuffer: 32 * 1024 * 1024,
    });

    try {
        return JSON.parse(out) as T[];
    } catch {
        throw new Error(`db-query failed for: ${sql}
${out.trim().slice(0, 800)}`);
    }
}

export function dbValue<T = unknown>(sql: string, bindings: unknown[] = []): T {
    const row = dbQuery(sql, bindings)[0];

    return (row === undefined ? undefined : Object.values(row)[0]) as T;
}
