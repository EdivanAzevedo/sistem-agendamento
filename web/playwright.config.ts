import { defineConfig, devices } from '@playwright/test'

// Browser tests run against the production images (see .github/scripts/e2e.sh), never against
// the Vite dev server: the Content Security Policy only exists in production.
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  // One retry in CI absorbs network hiccups (the docs load Scalar from a CDN); a test that only
  // passes on retry is reported as flaky, not hidden.
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['github']] : 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8095',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
