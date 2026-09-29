import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { VerifyEmailPage } from '@/features/auth/pages/VerifyEmailPage';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { meFixture } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

function server(verified: boolean) {
  return fakeFetch([
    { method: 'GET', path: '/sanctum/csrf-cookie', status: 204 },
    { method: 'GET', path: '/api/v1/me', body: { data: meFixture({ email_verified_at: verified ? '2026-10-29T00:00:00Z' : null, memberships: [] }) } },
  ]);
}

describe('VerifyEmailPage', () => {
  it('names the email and sends a new link', async () => {
    const backend = server(false);
    backend.respond({ method: 'POST', path: '/api/v1/auth/email/verification-notification', status: 202 });
    const page = renderPage(() => <VerifyEmailPage />, { server: backend, path: '/verify-email' });

    expect(await screen.findByText('We sent a verification link to demo@uply.test. Open it to start using Uply.')).toBeInTheDocument();
    await page.user.click(screen.getByRole('button', { name: 'Send a new link' }));

    expect(await screen.findByText('A new verification link is on its way.')).toBeInTheDocument();
    expect(backend.requestsTo('POST', '/api/v1/auth/email/verification-notification')).toHaveLength(1);
  });

  it('logs out to use another account', async () => {
    const backend = server(false);
    backend.respond({ method: 'POST', path: '/api/v1/auth/logout', status: 204 });
    const page = renderPage(() => <VerifyEmailPage />, { server: backend, path: '/verify-email' });

    await page.user.click(await screen.findByRole('button', { name: 'Use another account' }));

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/login');
    });
    expect(backend.requestsTo('POST', '/api/v1/auth/logout')).toHaveLength(1);
  });

  it('leaves once the email is verified', async () => {
    const page = renderPage(() => <VerifyEmailPage />, { server: server(true), path: '/verify-email' });

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/');
    });
  });
});
