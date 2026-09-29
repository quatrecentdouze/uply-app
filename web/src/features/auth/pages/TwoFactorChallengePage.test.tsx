import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { TwoFactorChallengePage } from '@/features/auth/pages/TwoFactorChallengePage';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { renderPage } from '@/shared/testing/renderPage';

function server() {
  return fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]);
}

describe('TwoFactorChallengePage', () => {
  it('sends the authentication code and goes to the redirect', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/auth/two-factor-challenge', status: 204 });
    const page = renderPage(() => <TwoFactorChallengePage redirectTo="/monitors" />, { server: backend, path: '/two-factor-challenge' });

    await page.user.type(await screen.findByLabelText('Authentication code'), '123456');
    await page.user.click(screen.getByRole('button', { name: 'Verify' }));

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/monitors');
    });
    expect(backend.requestsTo('POST', '/api/v1/auth/two-factor-challenge')[0]?.body).toEqual({ code: '123456' });
  });

  it('switches to a recovery code', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/auth/two-factor-challenge', status: 204 });
    const page = renderPage(() => <TwoFactorChallengePage redirectTo="/" />, { server: backend, path: '/two-factor-challenge' });

    await page.user.click(await screen.findByRole('button', { name: 'Use a recovery code' }));
    await page.user.type(screen.getByLabelText('Recovery code'), 'abcd-efgh');
    await page.user.click(screen.getByRole('button', { name: 'Verify' }));

    await waitFor(() => {
      expect(backend.requestsTo('POST', '/api/v1/auth/two-factor-challenge')[0]?.body).toEqual({ recovery_code: 'abcd-efgh' });
    });
  });

  it('shows a refused code under the field', async () => {
    const backend = server();
    backend.respond({
      method: 'POST',
      path: '/api/v1/auth/two-factor-challenge',
      status: 422,
      body: { message: 'Invalid.', errors: { code: ['The provided two factor authentication code was invalid.'] } },
    });
    const page = renderPage(() => <TwoFactorChallengePage redirectTo="/" />, { server: backend, path: '/two-factor-challenge' });

    await page.user.type(await screen.findByLabelText('Authentication code'), '000000');
    await page.user.click(screen.getByRole('button', { name: 'Verify' }));

    expect(await screen.findByText('The provided two factor authentication code was invalid.')).toBeInTheDocument();
  });

  it('asks for six digits before sending', async () => {
    const backend = server();
    const page = renderPage(() => <TwoFactorChallengePage redirectTo="/" />, { server: backend, path: '/two-factor-challenge' });

    await page.user.type(await screen.findByLabelText('Authentication code'), '12');
    await page.user.click(screen.getByRole('button', { name: 'Verify' }));

    expect(await screen.findByText('Enter the 6 digits of your code.')).toBeInTheDocument();
    expect(backend.requestsTo('POST', '/api/v1/auth/two-factor-challenge')).toEqual([]);
  });
});
