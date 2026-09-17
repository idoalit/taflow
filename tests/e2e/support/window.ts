import type { BrowserContextOptions } from '@playwright/test';

/** Where the recording of the whole story is written. */
export const RECORDING_DIR = 'storage/e2e-results/rekaman';

export const RECORDING_PATH = `${RECORDING_DIR}/cerita-lengkap.webm`;

/**
 * How the one window is set up.
 *
 * Kept here rather than only in the config's `use` block, because the window is
 * opened by a fixture and a context made by hand inherits none of it — not the
 * size, not the locale, and not the recording.
 */
export const WINDOW: BrowserContextOptions = {
    viewport: { width: 1440, height: 900 },
    locale: 'id-ID',
    timezoneId: 'Asia/Jakarta',
    recordVideo: { dir: RECORDING_DIR, size: { width: 1440, height: 900 } },
};
