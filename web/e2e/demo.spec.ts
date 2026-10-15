import { expect, test } from '@playwright/test';
import { demo } from './support/session';

test('the demo owner signs in and sees a filled dashboard', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill(demo.owner.email);
  await page.getByLabel('Password').fill(demo.owner.password);
  await page.getByRole('button', { name: 'Sign in' }).click();

  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page).toHaveTitle('Dashboard · Uply');
  const states = page.getByRole('region', { name: 'Monitor states' });
  await expect(states.locator('p').first()).toHaveText(/^[1-9]\d* monitors?$/);
  await expect(page.getByRole('region', { name: 'Monitors' }).getByRole('link', { name: /^Open / }).first()).toBeVisible();
  await expect(page.getByRole('region', { name: 'Uptime' }).getByRole('img').first()).toBeVisible();
});
