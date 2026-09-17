import { execFileSync } from 'node:child_process';
import {
    existsSync,
    mkdirSync,
    renameSync,
    rmSync,
    writeFileSync,
} from 'node:fs';

/** Where the Vite hot file is parked while the suite runs. */
export const HOT_FILE = 'public/hot';

export const PARKED_HOT_FILE = 'storage/e2e-results/hot.parked';

/**
 * Wipe everything before the story starts: an empty schema, the roles and
 * permissions the app cannot boot without, one program studi and one Admin
 * Prodi. Nothing else — every other account and record is made through the UI.
 */
export default function globalSetup(): void {
    if (process.env.E2E_SKIP_BUILD !== '1') {
        execFileSync('npm', ['run', 'build'], { stdio: 'inherit' });
    }

    // Against the Vite dev server the page reloads itself mid-test whenever HMR
    // reconnects, so the suite runs on the build it just made. The dev server
    // itself is left alone; only its hot file is parked, and put back after.
    if (existsSync(HOT_FILE)) {
        mkdirSync('storage/e2e-results', { recursive: true });
        renameSync(HOT_FILE, PARKED_HOT_FILE);
    }

    execFileSync(
        'php',
        [
            'artisan',
            'migrate:fresh',
            '--seed',
            '--seeder=Database\\Seeders\\E2EBootstrapSeeder',
            '--force',
            '--no-interaction',
        ],
        { stdio: 'inherit' },
    );

    // Uploaded proposals, signatures and imports from an earlier run.
    rmSync('storage/app/private/thesis', { recursive: true, force: true });
    rmSync('storage/app/private/signatures', { recursive: true, force: true });

    // The reset links the tests read are mailed to the log, so it starts empty.
    writeFileSync('storage/logs/laravel.log', '');
}
