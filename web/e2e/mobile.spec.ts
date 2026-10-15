import { expect, type Page, test } from '@playwright/test';
import { demoStatusPageSlug, ensureDemoStatusPage } from './support/demo';
import { demo, signInByApi } from './support/session';

async function expectNoHorizontalScroll(page: Page, screen: string): Promise<void> {
  await expect(page.locator('main')).toBeVisible();
  await expect(page.locator('[data-slot="skeleton"]')).toHaveCount(0);
  const widths = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
  expect(widths.scroll, `${screen} scrolls sideways`).toBeLessThanOrEqual(widths.client);
}

async function openFromDrawer(page: Page, name: string): Promise<void> {
  await page.getByRole('button', { name: 'Open navigation' }).click();
  await page.getByRole('dialog', { name: 'Pages' }).getByRole('link', { name: new RegExp(`^${name}`) }).click();
  await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
  await expectNoHorizontalScroll(page, name);
}

test('the public screens fit 375 px without a horizontal scroll', async ({ page, browser }) => {
  await ensureDemoStatusPage(browser);
  for (const path of ['/', '/login', `/status/${demoStatusPageSlug}`]) {
    await page.goto(path);
    await expectNoHorizontalScroll(page, path);
  }
});

test('the app screens fit 375 px without a horizontal scroll', async ({ page, context }) => {
  await signInByApi(context, demo.owner);
  await page.goto('/dashboard');
  await expectNoHorizontalScroll(page, 'Dashboard');

  for (const name of ['Monitors', 'Incidents', 'Alert channels', 'Status pages']) {
    await openFromDrawer(page, name);
  }

  await openFromDrawer(page, 'Monitors');
  await page.getByRole('table', { name: 'Monitors of the organization' }).getByRole('link').first().click();
  await expect(page).toHaveURL(/\/monitors\/[a-z0-9]+$/);
  await expectNoHorizontalScroll(page, 'Monitor detail');

  await openFromDrawer(page, 'Settings');
  for (const tab of ['Members', 'Profile', 'Security']) {
    await page.getByRole('link', { name: tab, exact: true }).click();
    await expectNoHorizontalScroll(page, `Settings ${tab}`);
  }
});
