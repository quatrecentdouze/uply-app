import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ForgotPasswordPage } from '@/features/auth/pages/ForgotPasswordPage';
import { ResetPasswordPage } from '@/features/auth/pages/ResetPasswordPage';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { renderPage } from '@/shared/testing/renderPage';

function server() {
  return fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]);
}

describe('ForgotPasswordPage', () => {
  it('asks for a reset link and says it is on its way', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/auth/forgot-password', body: { message: 'sent' } });
    const page = renderPage(() => <ForgotPasswordPage />, { server: backend, path: '/forgot-password' });

    await page.user.type(await screen.findByLabelText('Email'), 'demo@uply.test');
    await page.user.click(screen.getByRole('button', { name: 'Send reset link' }));

    expect(await screen.findByText('Check your inbox: the reset link is on its way.')).toBeInTheDocument();
    expect(backend.requestsTo('POST', '/api/v1/auth/forgot-password')[0]?.body).toEqual({ email: 'demo@uply.test' });
  });
});

describe('ResetPasswordPage', () => {
  it('sends the token of the link with the new password and goes to sign in', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/auth/reset-password', body: { message: 'reset' } });
    const page = renderPage(() => <ResetPasswordPage link={{ token: 'reset-token', email: 'demo@uply.test' }} />, { server: backend, path: '/reset-password' });

    expect(await screen.findByLabelText('Email')).toHaveValue('demo@uply.test');
    await page.user.type(screen.getByLabelText('New password'), 'a new long password');
    await page.user.type(screen.getByLabelText('Confirm new password'), 'a new long password');
    await page.user.click(screen.getByRole('button', { name: 'Reset password' }));

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/login');
    });
    expect(page.router.state.location.search).toEqual({ notice: 'password-reset' });
    expect(backend.requestsTo('POST', '/api/v1/auth/reset-password')[0]?.body).toEqual({
      token: 'reset-token',
      email: 'demo@uply.test',
      password: 'a new long password',
      password_confirmation: 'a new long password',
    });
  });

  it('explains an incomplete link', async () => {
    renderPage(() => <ResetPasswordPage link={null} />, { server: server(), path: '/reset-password' });

    expect(await screen.findByText('This reset link is incomplete. Ask for a new one.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ask for a new link' })).toHaveAttribute('href', '/forgot-password');
  });
});
