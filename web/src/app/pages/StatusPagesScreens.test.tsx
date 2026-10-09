import { screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import type { Role, StatusPage } from '@/shared/api/resources';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { listOf, meFixture, monitorFixture, organizationId } from '@/shared/testing/fixtures';

const base = `/api/v1/organizations/${organizationId}/status-pages`;

const page: StatusPage = {
  id: 'page-1',
  slug: 'acme-status',
  title: 'Acme status',
  description: null,
  is_published: false,
  monitors: [
    { monitor_id: 'mon-api', display_name: 'API', position: 0 },
    { monitor_id: 'mon-site', display_name: 'Website', position: 1 },
  ],
  created_at: null,
  updated_at: null,
};

function open(path: string, role: Role = 'owner') {
  const me = meFixture({ memberships: [{ id: 'm1', role, organization: { id: organizationId, name: 'Demo', slug: 'demo' }, joined_at: null }] });
  const server = withWorkspace(signedIn(fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]), me), {
    monitors: [monitorFixture({ id: 'mon-api', name: 'Public API' }), monitorFixture({ id: 'mon-site', name: 'Marketing site' }), monitorFixture({ id: 'mon-search', name: 'Search' })],
  });
  server.respond({ method: 'GET', path: base, body: listOf([page]) });
  server.respond({ method: 'GET', path: `${base}/page-1`, body: { data: page } });
  return renderApp({ path, server });
}

describe('status pages list', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('lists the pages with their address and state', async () => {
    open('/status-pages');

    const table = within(await screen.findByRole('table', { name: 'Status pages of the organization' }));
    expect(table.getByRole('link', { name: 'Acme status' })).toHaveAttribute('href', '/status-pages/page-1');
    expect(table.getByText('/status/acme-status')).toBeInTheDocument();
    expect(table.getByText('Draft')).toBeInTheDocument();
  });

  it('creates a page with an address made from its title and opens its editor', async () => {
    const app = open('/status-pages');
    app.server.respond({ method: 'POST', path: base, status: 201, body: { data: { ...page, id: 'page-2', title: 'Acme Café', slug: 'acme-cafe', monitors: [] } } });

    await app.user.click(await screen.findByRole('button', { name: 'New status page' }));
    const dialog = within(await screen.findByRole('dialog', { name: 'New status page' }));
    await app.user.type(dialog.getByLabelText('Title'), 'Acme Café');
    expect(dialog.getByLabelText('Address')).toHaveValue('acme-cafe');
    await app.user.click(dialog.getByRole('button', { name: 'Create page' }));

    await waitFor(() => {
      expect(app.router.state.location.pathname).toBe('/status-pages/page-2');
    });
    expect(app.server.requestsTo('POST', base)[0]?.body).toEqual({ title: 'Acme Café', slug: 'acme-cafe', is_published: false });
  });

  it('says when the organization has reached its page limit', async () => {
    const app = open('/status-pages');
    app.server.respond({ method: 'POST', path: base, status: 409, body: { message: 'Limit.', code: 'status_page_limit_reached' } });

    await app.user.click(await screen.findByRole('button', { name: 'New status page' }));
    const dialog = within(await screen.findByRole('dialog'));
    await app.user.type(dialog.getByLabelText('Title'), 'One more');
    await app.user.click(dialog.getByRole('button', { name: 'Create page' }));

    expect(await dialog.findByText('This organization has reached its status page limit.')).toBeInTheDocument();
  });
});

describe('status page editor', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('reorders the monitors, names one, adds one, publishes and saves the whole list', async () => {
    const app = open('/status-pages/page-1');
    app.server.respond({ method: 'PATCH', path: `${base}/page-1`, body: { data: { ...page, is_published: true } } });

    await app.user.click(await screen.findByRole('button', { name: 'Move Marketing site up' }));
    const display = screen.getByRole('textbox', { name: 'Display name of Public API' });
    await app.user.clear(display);
    await app.user.type(display, 'Public API v2');
    await app.user.selectOptions(screen.getByLabelText('Add a monitor'), 'Search');
    await app.user.click(screen.getByRole('button', { name: 'Add' }));
    await app.user.click(screen.getByRole('switch', { name: 'Published' }));
    await app.user.click(screen.getByRole('button', { name: 'Save page' }));

    await waitFor(() => {
      expect(app.server.requestsTo('PATCH', `${base}/page-1`)[0]?.body).toEqual({
        is_published: true,
        monitors: [{ monitor_id: 'mon-site', display_name: 'Website' }, { monitor_id: 'mon-api', display_name: 'Public API v2' }, { monitor_id: 'mon-search' }],
      });
    });
  });

  it('shows a taken address under its field', async () => {
    const app = open('/status-pages/page-1');
    app.server.respond({ method: 'PATCH', path: `${base}/page-1`, status: 422, body: { message: 'Invalid.', errors: { slug: ['The slug has already been taken.'] } } });

    const slug = await screen.findByLabelText('Address');
    await app.user.clear(slug);
    await app.user.type(slug, 'taken-one');
    await app.user.click(screen.getByRole('button', { name: 'Save page' }));

    expect(await screen.findByText('The slug has already been taken.')).toBeInTheDocument();
  });

  it('deletes the page after the confirmation', async () => {
    const app = open('/status-pages/page-1');
    app.server.respond({ method: 'DELETE', path: `${base}/page-1`, status: 204 });

    await app.user.click(await screen.findByRole('button', { name: 'Delete page' }));
    await app.user.click(within(await screen.findByRole('alertdialog', { name: 'Delete Acme status?' })).getByRole('button', { name: 'Delete page' }));

    await waitFor(() => {
      expect(app.router.state.location.pathname).toBe('/status-pages');
    });
  });

  it('shows the page read only to a viewer', async () => {
    open('/status-pages/page-1', 'viewer');

    expect(await screen.findByLabelText('Title')).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Save page' })).toBeNull();
    expect(screen.queryByRole('button', { name: /Move/ })).toBeNull();
  });
});
