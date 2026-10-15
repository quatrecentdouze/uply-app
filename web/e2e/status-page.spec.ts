import { randomUUID } from 'node:crypto';
import { expect, test } from './support/fixtures';
import { createMonitor } from './support/session';

test('a status page is created and published, read without a session, and an unknown address is not found', async ({ page, context, browser, account, baseURL }) => {
  await createMonitor(context, account.organizationId, 'Public API');
  const slug = `e2e-${randomUUID().slice(0, 8)}`;

  await page.goto('/status-pages');
  await page.getByRole('button', { name: 'New status page' }).click();
  const dialog = page.getByRole('dialog', { name: 'New status page' });
  await dialog.getByLabel('Title').fill('E2E status');
  await dialog.getByLabel('Address').fill(slug);
  await dialog.getByRole('button', { name: 'Create page' }).click();

  await expect(page.getByLabel('Title')).toHaveValue('E2E status');
  await page.getByLabel('Add a monitor').selectOption({ label: 'Public API' });
  await page.getByRole('button', { name: 'Add', exact: true }).click();
  await page.getByRole('switch', { name: 'Published' }).click();
  await page.getByRole('button', { name: 'Save page' }).click();
  await expect(page.getByText('The page is saved.')).toBeVisible();
  await expect(page.getByRole('link', { name: /Open the public page/ })).toHaveAttribute('href', `/status/${slug}`);

  const visitor = await browser.newContext({ baseURL: baseURL ?? 'http://localhost:5173' });
  try {
    const publicPage = await visitor.newPage();
    await publicPage.goto(`/status/${slug}`);
    await expect(publicPage.getByRole('heading', { level: 1, name: 'E2E status' })).toBeVisible();
    await expect(publicPage.getByRole('heading', { name: 'Public API' })).toBeVisible();
    await expect(publicPage.getByRole('img', { name: /Uptime of Public API over the last 90 days/ })).toBeVisible();
    await expect(publicPage).toHaveTitle('E2E status · Uply');

    await publicPage.goto(`/status/${slug}-unknown`);
    await expect(publicPage.getByRole('heading', { name: 'Status page not found' })).toBeVisible();
  } finally {
    await visitor.close();
  }
});
