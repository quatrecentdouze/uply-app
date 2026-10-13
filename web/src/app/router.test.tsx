import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { guest, renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { meFixture, metaFixture, publicStatusPageFixture } from '@/shared/testing/fixtures';

async function landsOn(app: ReturnType<typeof renderApp>, href: string) {
  await waitFor(() => {
    expect(app.router.state.location.href).toBe(href);
  });
}

describe('entry guards', () => {
  it('sends a guest from a private page to sign in, with the way back', async () => {
    const app = renderApp({ path: '/incidents', server: guest() });

    await landsOn(app, '/login?redirect=%2Fincidents');
    expect(await screen.findByRole('heading', { name: 'Sign in to Uply' })).toBeInTheDocument();
    await waitFor(() => {
      expect(document.title).toBe('Sign in · Uply');
    });
  });

  it('sends an unverified user to the verify screen', async () => {
    const app = renderApp({ path: '/', server: signedIn(fakeFetch(), meFixture({ email_verified_at: null, memberships: [] })) });

    await landsOn(app, '/verify-email');
    expect(await screen.findByRole('heading', { name: 'Verify your email' })).toBeInTheDocument();
  });

  it('sends a user without organization to create one', async () => {
    const app = renderApp({ path: '/', server: signedIn(fakeFetch(), meFixture({ memberships: [] })) });

    await landsOn(app, '/organizations/new');
    expect(await screen.findByRole('heading', { name: 'Create your organization' })).toBeInTheDocument();
  });

  it('sends a member away from the guest screens', async () => {
    const app = renderApp({ path: '/login', server: signedIn(fakeFetch()) });

    await landsOn(app, '/dashboard');
  });

  it('sends a member from the root to the dashboard', async () => {
    const app = renderApp({ path: '/', server: withWorkspace(signedIn(fakeFetch())) });

    await landsOn(app, '/dashboard');
    await waitFor(() => {
      expect(document.title).toBe('Dashboard · Uply');
    });
  });

  it('keeps a member out of the verify screen', async () => {
    const app = renderApp({ path: '/verify-email', server: signedIn(fakeFetch()) });

    await landsOn(app, '/dashboard');
  });

  it('lets anyone open an invitation', async () => {
    const token = 'b'.repeat(40);
    const server = guest();
    server.respond({ method: 'GET', path: `/api/v1/invitations/${token}`, status: 404, body: { message: 'Not found.' } });
    const app = renderApp({ path: `/invitations/${token}`, server });

    expect(await screen.findByText('This invitation does not exist, was revoked or was already used.')).toBeInTheDocument();
    expect(app.router.state.location.pathname).toBe(`/invitations/${token}`);
  });

  it('shows the route error page when the session cannot load, then retries', async () => {
    const server = fakeFetch([{ method: 'GET', path: '/api/v1/me', status: 500, body: { message: 'Server Error' } }]);
    const app = renderApp({ path: '/login', server });

    expect(await screen.findByRole('heading', { name: 'This page could not load' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Back to the home page' })).toHaveAttribute('href', '/');

    guest(server);
    await app.user.click(screen.getByRole('button', { name: 'Try again' }));

    expect(await screen.findByRole('heading', { name: 'Sign in to Uply' })).toBeInTheDocument();
  });

  it('opens a public status page in the public layout without asking for the session', async () => {
    const server = fakeFetch([
      { method: 'GET', path: '/api/v1/public/status-pages/demo-services', body: { data: publicStatusPageFixture() } },
      { method: 'GET', path: '/api/v1/meta', body: { data: metaFixture() } },
    ]);
    renderApp({ path: '/status/demo-services', server });

    expect(await screen.findByRole('heading', { level: 1, name: 'Demo services' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Powered by Uply' })).toHaveAttribute('href', '/');
    expect(server.requestsTo('GET', '/api/v1/me')).toHaveLength(0);
  });

  it('opens the not found page of the router for an unknown address', async () => {
    renderApp({ path: '/nowhere', server: guest() });

    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument();
    await waitFor(() => {
      expect(document.title).toBe('Page not found · Uply');
    });
  });
});
