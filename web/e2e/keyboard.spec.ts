import { expect, test } from '@playwright/test';
import { demo } from './support/session';

test('the keyboard signs in, opens the palette with Ctrl K and closes a dialog with Escape', async ({ page }) => {
  await page.goto('/login');
  await expect(page.getByRole('heading', { name: 'Sign in to Uply' })).toBeVisible();

  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
  await page.keyboard.press('Enter');
  await page.keyboard.press('Tab');
  await expect(page.getByLabel('Email')).toBeFocused();
  await page.keyboard.type(demo.owner.email);
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  await expect(page.getByLabel('Password')).toBeFocused();
  await page.keyboard.type(demo.owner.password);
  await page.keyboard.press('Enter');

  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
  await page.getByRole('main').focus();

  await page.keyboard.press('Control+k');
  const palette = page.getByRole('dialog', { name: 'Search' });
  await expect(palette).toBeVisible();
  await page.keyboard.type('monitors');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/monitors$/);
  await expect(palette).toBeHidden();
  await expect(page.getByRole('heading', { level: 1, name: 'Monitors' })).toBeVisible();

  await page.getByRole('main').focus();
  await page.keyboard.press('Shift+?');
  const help = page.getByRole('dialog', { name: 'Keyboard shortcuts' });
  await expect(help).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(help).toBeHidden();
});
