import { expect, test } from '@playwright/test';
import { deleteAccount, testCredentials, verifyEmail } from './support/session';

test('a new user signs up, verifies the email, creates an organization and lands on an empty dashboard', async ({ page, context }) => {
  const account = testCredentials();
  try {
    await page.goto('/register');
    await page.getByLabel('Name').fill(account.name);
    await page.getByLabel('Email').fill(account.email);
    await page.getByLabel('Password', { exact: true }).fill(account.password);
    await page.getByLabel('Confirm password').fill(account.password);
    await page.getByRole('button', { name: 'Create account' }).click();

    await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible();
    await verifyEmail(context, account.email);

    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Create your organization' })).toBeVisible();
    await page.getByLabel('Organization name').fill(`${account.name} team`);
    await page.getByRole('button', { name: 'Create organization' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByText('No monitor yet. Add one to start watching a service.')).toBeVisible();
    await expect(page.getByText('No incident so far. Everything is running.')).toBeVisible();
  } finally {
    await deleteAccount(context, account);
  }
});
