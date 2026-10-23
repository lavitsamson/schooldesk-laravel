import { defineConfig, devices } from '@playwright/test';

/**
 * Locally with Sail, run against the running app:
 *   E2E_BASE_URL=http://localhost npx playwright test
 * Without E2E_BASE_URL (e.g. in CI), Playwright starts `php artisan serve`.
 */
const externalBaseUrl = process.env.E2E_BASE_URL;
const baseURL = externalBaseUrl ?? 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never' }]]
        : 'list',
    // Sail on a Windows-mounted folder is slow; raise via E2E_EXPECT_TIMEOUT (ms) if needed.
    expect: { timeout: Number(process.env.E2E_EXPECT_TIMEOUT ?? 5_000) },
    use: {
        baseURL,
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: externalBaseUrl
        ? undefined
        : {
              command: 'php artisan serve --host=127.0.0.1 --port=8000',
              url: baseURL,
              reuseExistingServer: !process.env.CI,
              timeout: 60_000,
          },
});
