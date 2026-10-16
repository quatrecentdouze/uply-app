import { fileURLToPath } from 'node:url';
import { expect, type Page, test } from '@playwright/test';
import { demoStatusPageSlug, ensureDemoStatusPage } from './support/demo';
import { demo, signInByApi } from './support/session';

const folder = fileURLToPath(new URL('../../.github/readme/', import.meta.url));
const loopbackTarget = /http:\/\/127\.0\.0\.1:8090/g;
const sampleTarget = 'https://demo.example.com';
const settleMilliseconds = 1_500;

test.use({ reducedMotion: 'reduce', colorScheme: 'dark' });

async function maskLoopbackTargets(page: Page): Promise<void> {
  await page.route('**/api/v1/organizations/**', async (route) => {
    const response = await route.fetch();
    const type = response.headers()['content-type'] ?? '';
    if (!type.includes('application/json')) {
      await route.fulfill({ response });
      return;
    }
    const parsed: unknown = JSON.parse(await response.text());
    await route.fulfill({ response, body: JSON.stringify(parsed).replace(loopbackTarget, sampleTarget) });
  });
}

async function capture(page: Page, name: string): Promise<void> {
  await expect(page.locator('main')).toBeVisible();
  await expect(page.locator('[data-slot="skeleton"]')).toHaveCount(0);
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(settleMilliseconds);
  expect(await page.content()).not.toMatch(loopbackTarget);
  await page.screenshot({ path: `${folder}${name}.png` });
}

test('the README screenshots', async ({ page, context, browser }) => {
  await ensureDemoStatusPage(browser);

  await page.goto('/');
  await capture(page, 'landing');

  await page.goto(`/status/${demoStatusPageSlug}`);
  await capture(page, 'status-page');

  await maskLoopbackTargets(page);
  await signInByApi(context, demo.owner);
  await page.goto('/dashboard');
  await capture(page, 'dashboard');

  await page.goto('/monitors');
  await page.getByRole('table', { name: 'Monitors of the organization' }).getByRole('link').first().click();
  await expect(page.getByRole('table', { name: 'Recent checks of the monitor' })).toBeVisible();
  await capture(page, 'monitor');

  await page.goto('/incidents');
  await expect(page.getByRole('table', { name: 'Incidents of the organization' })).toBeVisible();
  await capture(page, 'incidents');

  await page.goto('/status-pages');
  await page.getByRole('link', { name: 'Demo services' }).click();
  await expect(page.getByLabel('Title')).toHaveValue('Demo services');
  await capture(page, 'status-page-editor');

  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/dashboard');
  await capture(page, 'dashboard-mobile');
});
