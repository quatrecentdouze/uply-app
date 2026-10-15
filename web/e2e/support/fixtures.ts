import { test as base } from '@playwright/test';
import { createAccount, deleteAccount, type TestAccount } from './session';

export const test = base.extend<{ account: TestAccount }>({
  account: async ({ context }, use) => {
    const account = await createAccount(context);
    await use(account);
    await deleteAccount(context, account);
  },
});

export { expect } from '@playwright/test';
