import { expect, test } from './support/fixtures';

test('a monitor is created, fills its detail at the first check, pauses and is deleted', async ({ page, account }) => {
  await page.goto('/monitors');
  await expect(page.getByText('No monitor yet. Add one to start watching a service.')).toBeVisible();

  await page.getByRole('button', { name: 'Add monitor' }).click();
  const dialog = page.getByRole('dialog', { name: 'Add a monitor' });
  await dialog.getByLabel('Name').fill('Local web app');
  await dialog.getByLabel('URL').fill('http://127.0.0.1:5173/');
  await dialog.getByLabel('Check interval').selectOption({ label: 'Every 30 seconds' });
  await dialog.getByRole('button', { name: 'Add monitor' }).click();

  await expect(page).toHaveURL(/\/monitors\/[a-z0-9]+$/);
  await expect(page.getByRole('heading', { level: 2, name: 'Local web app' })).toBeVisible();
  const checks = page.getByRole('table', { name: 'Recent checks of the monitor' });
  await expect(checks.getByRole('row').nth(1)).toBeVisible({ timeout: 75_000 });
  await expect(checks.getByText('Up').first()).toBeVisible();

  const pause = page.getByRole('switch', { name: 'Paused' });
  await pause.click();
  await expect(pause).toBeChecked();

  await page.getByRole('button', { name: 'Delete' }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Delete monitor' }).click();
  await expect(page).toHaveURL(/\/monitors$/);
  await expect(page.getByText('No monitor yet. Add one to start watching a service.')).toBeVisible();
  expect(account.email).toMatch(/^e2e-/);
});
