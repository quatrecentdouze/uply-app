import { screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { incidentFixture, listOf, organizationId } from '@/shared/testing/fixtures';

const base = `/api/v1/organizations/${organizationId}`;

const screens: readonly [string, string, string, unknown][] = [
  ['the incidents', '/incidents', `${base}/incidents`, listOf([])],
  ['an incident', '/incidents/inc-1', `${base}/incidents/inc-1`, { data: incidentFixture({ id: 'inc-1', timeline: [] }) }],
  ['the alert channels', '/alert-channels', `${base}/alert-channels`, listOf([])],
  ['the status pages', '/status-pages', `${base}/status-pages`, listOf([])],
  ['the members', '/settings/members', `${base}/members`, listOf([])],
  ['the invitations', '/settings/members', `${base}/invitations`, listOf([])],
];

describe('screens that fail to load', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it.each(screens)('offers a retry on %s, then shows them', async (_, path, failing, body) => {
    const server = withWorkspace(signedIn(fakeFetch()));
    server.respond({ method: 'GET', path: `${base}/members`, body: listOf([]) });
    server.respond({ method: 'GET', path: `${base}/invitations`, body: listOf([]) });
    server.respond({ method: 'GET', path: failing, status: 500, body: { message: 'Server Error' } });
    const app = renderApp({ path, server });

    const retry = await screen.findByRole('button', { name: 'Retry' });
    server.respond({ method: 'GET', path: failing, body });
    await app.user.click(retry);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Retry' })).toBeNull();
    });
  });
});
