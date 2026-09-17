import { existsSync, renameSync } from 'node:fs';
import { HOT_FILE, PARKED_HOT_FILE } from './global-setup';

/** Hand the Vite dev server its hot file back. */
export default function globalTeardown(): void {
    if (existsSync(PARKED_HOT_FILE)) {
        renameSync(PARKED_HOT_FILE, HOT_FILE);
    }
}
