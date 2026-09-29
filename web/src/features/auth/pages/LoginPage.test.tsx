import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { LoginPage } from '@/features/auth/pages/LoginPage';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { renderPage } from '@/shared/testing/renderPage';

function server() {
  return fakeFetch([
    { method: 'GET', path: '/sanctum/csrf-cookie', status: 204 },
    { method: 'GET', path: '/api/v1/me', status: 401, body: { message: 'Unauthenticated.' } },
  ]);
}

async function signIn(page: ReturnType<typeof renderPage>, email = 'demo@uply.test', password = 'password') {
  await page.user.type(await screen.findByLabelText('Email'), email);
  await page.user.type(screen.getByLabelText('Password'), password);
  await page.user.click(screen.getByRole('button', { name: 'Sign in' }));
}

describe('LoginPage', () => {
  it('signs in with the XSRF header and goes to the redirect', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/auth/login', body: { two_factor: false } });
    const page = renderPage(() => <LoginPage redirectTo="/invitations/abc" notice={null} />, { server: backend, path: '/login' });

    await signIn(page);

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/invitations/abc');
    });
    const login = backend.requestsTo('POST', '/api/v1/auth/login')[0];
    expect(login?.body).toEqual({ email: 'demo@uply.test', password: 'password' });
    expect(login?.headers.get('X-XSRF-TOKEN')).toBe('test-token');
  });

  it('goes to the two factor challenge when the account asks for it', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/auth/login', body: { two_factor: true } });
    const page = renderPage(() => <LoginPage redirectTo="/" notice={null} />, { server: backend, path: '/login' });

    await signIn(page);

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/two-factor-challenge');
    });
  });

  it('shows the field error of a refused login', async () => {
    const backend = server();
    backend.respond({
      method: 'POST',
      path: '/api/v1/auth/login',
      status: 422,
      body: { message: 'Invalid.', errors: { email: ['These credentials do not match our records.'] } },
    });
    const page = renderPage(() => <LoginPage redirectTo="/" notice={null} />, { server: backend, path: '/login' });

    await signIn(page);

    expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument();
    expect(page.router.state.location.pathname).toBe('/login');
  });

  it('checks the fields before sending anything', async () => {
    const backend = server();
    const page = renderPage(() => <LoginPage redirectTo="/" notice={null} />, { server: backend, path: '/login' });

    await page.user.click(await screen.findByRole('button', { name: 'Sign in' }));

    expect(await screen.findAllByText('This field is required.')).toHaveLength(2);
    expect(backend.requestsTo('POST', '/api/v1/auth/login')).toEqual([]);
  });

  it('shows the text of a rate limited login', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/auth/login', status: 429, body: { message: 'Too Many Attempts.' } });
    const page = renderPage(() => <LoginPage redirectTo="/" notice={null} />, { server: backend, path: '/login' });

    await signIn(page);

    expect(await screen.findByText('Too many attempts. Please wait a moment and try again.')).toBeInTheDocument();
  });

  it('confirms a password reset', async () => {
    renderPage(() => <LoginPage redirectTo="/" notice="password-reset" />, { server: server(), path: '/login' });

    expect(await screen.findByText('Your password was reset. Sign in with the new one.')).toBeInTheDocument();
  });
});
