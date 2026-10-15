import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:5173';
const onCi = process.env.CI !== undefined;

const desktop = { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } };

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: onCi,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: onCi ? [['list'], ['html', { open: 'never' }]] : [['list']],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', testIgnore: [/mobile\.spec\.ts/, /readme\.spec\.ts/], use: desktop },
    { name: 'mobile', testMatch: /mobile\.spec\.ts/, use: { ...devices['Desktop Chrome'], viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true } },
    { name: 'readme', testMatch: /readme\.spec\.ts/, use: desktop },
  ],
});
