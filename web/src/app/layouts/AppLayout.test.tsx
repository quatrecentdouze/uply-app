import { act, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import { queryKeys } from '@/shared/api/queryKeys';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { meFixture, monitorFixture, organizationId } from '@/shared/testing/fixtures';

function openApp(path = '/', openIncidents = 2) {
  const server = withWorkspace(signedIn(fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }])), {
    monitors: [monitorFixture({ id: 'mon-api', name: 'Public API' }), monitorFixture({ id: 'mon-site', name: 'Marketing site', url: 'https://www.example.test' })],
    openIncidents,
  });
  return renderApp({ path, server });
}

async function sidebar() {
  return within(await screen.findByRole('complementary', { name: 'Main navigation' }));
}

describe('app shell', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('lists the navigation groups with the open incidents badge', async () => {
    openApp();
    const navigation = await sidebar();

    for (const group of ['Monitoring', 'Alerting', 'General']) {
      expect(navigation.getByRole('heading', { name: group })).toBeInTheDocument();
    }
    expect(navigation.getByRole('link', { name: /Dashboard/ })).toHaveAttribute('data-status', 'active');
    expect(await navigation.findByLabelText('2 open incidents')).toHaveTextContent('2');
    expect(navigation.getByRole('button', { name: /Demo/ })).toBeInTheDocument();
  });

  it('shows the page title, the profile and the red dot of the bell', async () => {
    openApp();

    expect(await screen.findByRole('heading', { level: 1, name: 'Dashboard' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Open the profile menu of Demo User' })).toHaveTextContent('DU');
    expect(await screen.findByRole('link', { name: 'Notifications, an incident is open' })).toBeInTheDocument();
  });

  it('hides the red dot without an open incident', async () => {
    openApp('/', 0);

    expect(await screen.findByRole('link', { name: 'Notifications' })).toBeInTheDocument();
    expect(screen.queryByTestId('open-incident-dot')).toBeNull();
  });

  it('opens the palette with Ctrl K and goes to a page', async () => {
    const app = openApp();
    await sidebar();

    await app.user.keyboard('{Control>}k{/Control}');
    const palette = within(await screen.findByRole('dialog'));
    expect(await palette.findByText('Marketing site')).toBeInTheDocument();
    await app.user.click(palette.getByText('Alert channels'));

    await waitFor(() => {
      expect(app.router.state.location.pathname).toBe('/alert-channels');
    });
  });

  it('opens a monitor from the palette', async () => {
    const app = openApp();
    await sidebar();

    await app.user.keyboard('{Control>}k{/Control}');
    await app.user.click(await within(await screen.findByRole('dialog')).findByText('Public API'));

    await waitFor(() => {
      expect(app.router.state.location.pathname).toBe('/monitors/mon-api');
    });
  });

  it('shows the keyboard shortcuts with the help entry of the sidebar and with ?', async () => {
    const app = openApp();
    await app.user.click((await sidebar()).getByRole('button', { name: 'Help' }));

    expect(await screen.findByRole('dialog', { name: 'Keyboard shortcuts' })).toBeInTheDocument();
    await app.user.keyboard('{Escape}');
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });

    await app.user.keyboard('?');
    expect(await screen.findByRole('dialog', { name: 'Keyboard shortcuts' })).toBeInTheDocument();
  });

  it('keeps help and log out in the sidebar only', async () => {
    const app = openApp();
    await sidebar();

    expect(screen.getAllByRole('button', { name: 'Help' })).toHaveLength(1);
    await app.user.click(screen.getByRole('button', { name: 'Open the profile menu of Demo User' }));

    expect(await screen.findByRole('menuitem', { name: 'Profile settings' })).toHaveAttribute('href', '/settings/profile');
    expect(screen.getByRole('menuitem', { name: 'Security settings' })).toHaveAttribute('href', '/settings/security');
    expect(screen.queryByRole('menuitem', { name: 'Log out' })).toBeNull();
  });

  it('logs out from the sidebar', async () => {
    const app = openApp();
    app.server.respond({ method: 'POST', path: '/api/v1/auth/logout', status: 204 });

    const navigation = await sidebar();
    app.server.respond({ method: 'GET', path: '/api/v1/organizations', status: 401, body: { message: 'Unauthenticated.' } });
    await app.user.click(navigation.getByRole('button', { name: 'Log out' }));

    await waitFor(() => {
      expect(app.router.state.location.pathname).toBe('/login');
    });
    expect(app.router.state.location.href).toBe('/login');
    expect(app.server.requestsTo('POST', '/api/v1/auth/logout')).toHaveLength(1);
  });

  it('switches the organization and remembers it', async () => {
    const me = meFixture({
      memberships: [
        { id: 'm1', role: 'owner', organization: { id: organizationId, name: 'Demo', slug: 'demo' }, joined_at: null },
        { id: 'm2', role: 'viewer', organization: { id: 'org-acme', name: 'Acme', slug: 'acme' }, joined_at: null },
      ],
    });
    const server = withWorkspace(signedIn(fakeFetch(), me));
    const app = renderApp({ path: '/', server });

    await app.user.click(await (await sidebar()).findByRole('button', { name: /current one Demo/ }));
    await app.user.click(await screen.findByRole('menuitemradio', { name: 'Acme' }));

    expect(await (await sidebar()).findByRole('button', { name: /current one Acme/ })).toBeInTheDocument();
    expect(window.localStorage.getItem('uply.organization')).toBe('org-acme');
  });

  it('never sends a member to create an organization when the list fails to load', async () => {
    const app = openApp();
    await sidebar();
    app.server.respond({ method: 'GET', path: '/api/v1/organizations', status: 401, body: { message: 'Unauthenticated.' } });

    await act(async () => {
      await app.queryClient.resetQueries({ queryKey: queryKeys.organizations() });
      await new Promise((resolve) => setTimeout(resolve, 100));
    });

    expect(app.router.state.location.pathname).toBe('/');
  });

  it('opens the not found page inside the shell for a screen of the next milestone', async () => {
    const app = openApp();
    await app.user.click(await (await sidebar()).findByRole('link', { name: /Monitors/ }));

    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 1, name: 'Monitors' })).toBeInTheDocument();
  });
});
