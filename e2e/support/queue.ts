import { execFileSync } from 'node:child_process';
import { phpEnv } from './env';

/**
 * Heavy work goes through the queue (never the HTTP request); the e2e server runs no worker, so a test that waits for
 * a job's effect works the queue of the e2e database until it is empty — what the production worker would do.
 */
export function runQueue(): string {
    return execFileSync('php', ['artisan', 'queue:work', '--stop-when-empty', '--tries=1', '--timeout=300'], {
        env: phpEnv(),
        encoding: 'utf8',
        timeout: 600_000,
    });
}
