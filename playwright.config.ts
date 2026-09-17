import { defineConfig, devices } from '@playwright/test';
import { WINDOW } from './tests/e2e/support/window';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8001';

/** Slow the cursor down when a person is watching (npm run e2e:watch). */
const slowMo = Number(process.env.E2E_SLOWMO ?? 0);

/**
 * The end-to-end suite walks one story from an empty database: an Admin Prodi
 * sets the program studi up, imports accounts, and every role after that can
 * only act once the role before it has. The steps therefore run in order, in a
 * single worker, against one shared database.
 *
 * Run `npm run e2e:watch` to watch it happen in a real window.
 */
export default defineConfig({
    testDir: './tests/e2e',
    // One story, told in order: parallel workers would race on the same rows.
    fullyParallel: false,
    workers: 1,
    // A flaky retry would replay steps against state the first attempt changed.
    retries: 0,
    // Watching stretches every step, and one test signs for four people in a
    // row, so the budget grows with the delay rather than being tuned for the
    // headless case and failing the one a person actually sits through.
    timeout: slowMo > 0 ? 240_000 : 60_000,
    expect: { timeout: slowMo > 0 ? 30_000 : 10_000 },
    globalSetup: './tests/e2e/support/global-setup.ts',
    globalTeardown: './tests/e2e/support/global-teardown.ts',
    reporter: [
        ['list'],
        ['html', { outputFolder: 'storage/e2e-report', open: 'never' }],
    ],
    outputDir: 'storage/e2e-results',
    use: {
        baseURL,
        // The window itself is opened by the `app` fixture, which is where its
        // size, locale and recording are set; these only cover anything that
        // reaches for Playwright's own page fixture.
        ...WINDOW,
        recordVideo: undefined,
        trace: 'on',
        screenshot: 'on',
        actionTimeout: slowMo > 0 ? 45_000 : 15_000,
        launchOptions: { slowMo },
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8001',
        url: baseURL,
        reuseExistingServer: true,
        timeout: 30_000,
    },
});
