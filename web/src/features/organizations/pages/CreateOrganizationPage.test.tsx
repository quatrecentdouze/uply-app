import { screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { CreateOrganizationPage } from '@/features/organizations/pages/CreateOrganizationPage';
import { storageKeys } from '@/shared/storage/storage';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { renderPage } from '@/shared/testing/renderPage';

const created = { id: '01jbxneworganization000000', name: 'Acme', slug: 'acme', role: 'owner', created_at: '2026-10-29T00:00:00Z' };

function server() {
  return fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]);
}

describe('CreateOrganizationPage', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('creates the organization, makes it current and opens the dashboard', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/organizations', status: 201, body: { data: created } });
    const page = renderPage(() => <CreateOrganizationPage canCancel={false} />, { server: backend, path: '/organizations/new' });

    await page.user.type(await screen.findByLabelText('Organization name'), 'Acme');
    await page.user.click(screen.getByRole('button', { name: 'Create organization' }));

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/dashboard');
    });
    expect(backend.requestsTo('POST', '/api/v1/organizations')[0]?.body).toEqual({ name: 'Acme' });
    expect(window.localStorage.getItem(storageKeys.organization)).toBe(created.id);
  });

  it('shows the text of the organization limit', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: '/api/v1/organizations', status: 409, body: { message: 'Limit.', code: 'organization_limit_reached' } });
    const page = renderPage(() => <CreateOrganizationPage canCancel={false} />, { server: backend, path: '/organizations/new' });

    await page.user.type(await screen.findByLabelText('Organization name'), 'Acme');
    await page.user.click(screen.getByRole('button', { name: 'Create organization' }));

    expect(await screen.findByText('You own the maximum number of organizations.')).toBeInTheDocument();
  });

  it('offers a way back only to a member', async () => {
    const first = renderPage(() => <CreateOrganizationPage canCancel={false} />, { server: server(), path: '/organizations/new' });
    await screen.findByLabelText('Organization name');
    expect(screen.queryByRole('link', { name: 'Back to the dashboard' })).toBeNull();
    first.view.unmount();

    renderPage(() => <CreateOrganizationPage canCancel />, { server: server(), path: '/organizations/new' });
    expect(await screen.findByRole('link', { name: 'Back to the dashboard' })).toHaveAttribute('href', '/dashboard');
  });
});
