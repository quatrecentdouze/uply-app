import { expect, test } from '@playwright/test';
import { demo, signInByApi } from './support/session';

test('the viewer sees every screen and no write action', async ({ page, context }) => {
  await signInByApi(context, demo.viewer);

  await page.goto('/monitors');
  await expect(page.getByRole('table', { name: 'Monitors of the organization' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Add monitor' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: /^Actions for / })).toHaveCount(0);

  await page.getByRole('table', { name: 'Monitors of the organization' }).getByRole('link').first().click();
  await expect(page.getByRole('heading', { level: 2 }).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Edit' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Delete' })).toHaveCount(0);
  await expect(page.getByRole('switch', { name: 'Paused' })).toHaveCount(0);

  await page.goto('/alert-channels');
  await expect(page.getByRole('heading', { level: 1, name: 'Alert channels' })).toBeVisible();
  await expect(page.getByRole('button', { name: /Add|New/ })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Send test' })).toHaveCount(0);

  await page.goto('/status-pages');
  await expect(page.getByRole('heading', { level: 1, name: 'Status pages' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'New status page' })).toHaveCount(0);

  await page.goto('/settings/members');
  await expect(page.getByRole('table', { name: 'Members of the organization' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Send invitation' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Remove' })).toHaveCount(0);
});
