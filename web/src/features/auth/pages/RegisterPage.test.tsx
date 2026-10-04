import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { RegisterPage } from '@/features/auth/pages/RegisterPage';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { metaFixture } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

function server() {
  return fakeFetch([
    { method: 'GET', path: '/sanctum/csrf-cookie', status: 204 },
    { method: 'GET', path: '/api/v1/meta', body: { data: metaFixture() } },
  ]);
}

async function fill(page: ReturnType<typeof renderPage>, password: string, confirmation: string) {
  await page.user.type(await screen.findByLabelText('Name'), 'Ada Lovelace');
  await page.user.type(screen.getByLabelText('Email'), 'ada@example.test');
  await page.user.type(screen.getByLabelText('Password'), password);
  await page.user.type(screen.getByLabelText('Confirm password'), confirmation);
  await page.user.click(screen.getByRole('button', { name: 'Create account' }));
}

describe('RegisterPage', () => {
  it('creates the account and goes on', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/auth/register', status: 201, body: {} });
    const page = renderPage(() => <RegisterPage redirectTo="/" />, { server: backend, path: '/register' });

    await fill(page, 'a long password', 'a long password');

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/');
    });
    expect(backend.requestsTo('POST', '/api/v1/auth/register')[0]?.body).toEqual({
      name: 'Ada Lovelace',
      email: 'ada@example.test',
      password: 'a long password',
      password_confirmation: 'a long password',
    });
  });

  it('shows the password bounds from meta and checks them', async () => {
    const backend = server();
    const page = renderPage(() => <RegisterPage redirectTo="/" />, { server: backend, path: '/register' });

    expect(await screen.findByText('12 to 255 characters.')).toBeInTheDocument();
    await fill(page, 'short', 'other');

    expect(await screen.findByText('Use 12 to 255 characters.')).toBeInTheDocument();
    expect(screen.getByText('The passwords do not match.')).toBeInTheDocument();
    expect(backend.requestsTo('POST', '/api/v1/auth/register')).toEqual([]);
  });

  it('puts a server field error under its field', async () => {
    const backend = server();
    backend.respond({
      method: 'POST',
      path: '/api/v1/auth/register',
      status: 422,
      body: { message: 'Invalid.', errors: { email: ['The email has already been taken.'] } },
    });
    const page = renderPage(() => <RegisterPage redirectTo="/" />, { server: backend, path: '/register' });

    await fill(page, 'a long password', 'a long password');

    expect(await screen.findByText('The email has already been taken.')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
  });
});
