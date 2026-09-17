import { rmSync } from 'node:fs';
import { test as base, type Page } from '@playwright/test';
import { RECORDING_DIR, RECORDING_PATH, WINDOW } from './window';

/**
 * One browser window for the whole story, from the empty database to the
 * released result.
 *
 * Every part drives this same window and signs out before signing in as the
 * next person, which is both how a handover between roles really happens and
 * the only way to watch the run without windows piling up on top of each other.
 *
 * One window also means one recording: the traces stay per test, but the video
 * is a single film of the whole story rather than forty-four fragments.
 */
export const test = base.extend<object, { app: Page }>({
    app: [
        async ({ browser }, use) => {
            rmSync(RECORDING_DIR, { recursive: true, force: true });

            const context = await browser.newContext(WINDOW);
            const page = await context.newPage();
            const video = page.video();

            await use(page);

            // The file is only finished once the context is gone.
            await context.close();
            await video?.saveAs(RECORDING_PATH);

            // saveAs copies; without this the hashed original sits beside it.
            await video?.delete();
        },
        { scope: 'worker' },
    ],
});

export { expect } from '@playwright/test';
