import { expect, test } from './support/fixtures';

test('a monitor is created with a keyword and a header, fills its detail at the first check, drops its headers, pauses and is deleted', async ({ page, account }) => {
  const headerValue = `e2e-secret-${String(Date.now())}`;
  await page.goto('/monitors');
  await expect(page.getByText('No monitor yet. Add one to start watching a service.')).toBeVisible();

  await page.getByRole('button', { name: 'Add monitor' }).click();
  const dialog = page.getByRole('dialog', { name: 'Add a monitor' });
  await dialog.getByLabel('Name', { exact: true }).fill('Local web app');
  await dialog.getByLabel('URL').fill('http://127.0.0.1:5173/');
  await dialog.getByLabel('Check interval').selectOption({ label: 'Every 30 seconds' });
  await dialog.getByRole('button', { name: /Advanced/ }).click();
  await dialog.getByRole('textbox', { name: 'Keyword' }).fill('id="root"');
  await dialog.getByRole('button', { name: 'Add header' }).click();
  const header = dialog.getByRole('group', { name: 'Header 1' });
  await header.getByLabel('Header name').fill('X-Uply-Check');
  await header.getByLabel('Header value').fill(headerValue);
  await dialog.getByRole('spinbutton', { name: 'Failures before down' }).fill('1');
  await dialog.getByRole('spinbutton', { name: 'Successes before up' }).fill('1');
  await dialog.getByRole('button', { name: 'Add monitor' }).click();

  await expect(page).toHaveURL(/\/monitors\/[a-z0-9]+$/);
  await expect(page.getByRole('heading', { level: 2, name: 'Local web app' })).toBeVisible();
  const checks = page.getByRole('table', { name: 'Recent checks of the monitor' });
  await expect(checks.getByRole('row').nth(1)).toBeVisible({ timeout: 75_000 });
  await expect(checks.getByText('Up').first()).toBeVisible();

  const settings = page.getByRole('region', { name: 'Check settings' });
  await expect(settings.getByRole('list', { name: 'Request header names' })).toHaveText('X-Uply-Check');
  await expect(settings.getByText('id="root"')).toBeVisible();
  await expect(settings.getByText('1 check in a row')).toHaveCount(2);
  await expect(page.locator('body')).not.toContainText(headerValue);

  await page.getByRole('button', { name: 'Edit' }).click();
  const edit = page.getByRole('dialog', { name: 'Edit Local web app' });
  await expect(edit.getByRole('list', { name: 'Stored headers' })).toHaveText('X-Uply-Check');
  await edit.getByRole('button', { name: 'Replace headers' }).click();
  await expect(edit.getByRole('group', { name: 'Header 1' }).getByLabel('Header value')).toHaveValue('');
  await edit.getByRole('button', { name: 'Remove header 1' }).click();
  await edit.getByRole('button', { name: 'Save changes' }).click();
  await expect(edit).toBeHidden();
  await expect(settings.getByRole('list', { name: 'Request header names' })).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText(headerValue);

  const pause = page.getByRole('switch', { name: 'Paused' });
  await pause.click();
  await expect(pause).toBeChecked();

  await page.getByRole('button', { name: 'Delete' }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Delete monitor' }).click();
  await expect(page).toHaveURL(/\/monitors$/);
  await expect(page.getByText('No monitor yet. Add one to start watching a service.')).toBeVisible();
  expect(account.email).toMatch(/^e2e-/);
});
