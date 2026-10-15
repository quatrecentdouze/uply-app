import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { guest, renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import {
  alertChannelFixture,
  incidentFixture,
  listOf,
  metaFixture,
  monitorFixture,
  organizationId,
  publicStatusPageFixture,
} from '@/shared/testing/fixtures';

const base = `/api/v1/organizations/${organizationId}`;
const roles = ['button', 'link', 'textbox', 'combobox', 'switch', 'checkbox', 'tab', 'searchbox'] as const;

function workspace() {
  const server = withWorkspace(signedIn(fakeFetch()), {
    monitors: [monitorFixture({ id: 'mon-1' }), monitorFixture({ id: 'mon-2', name: 'Billing API', state: 'down', is_paused: true })],
    incidents: [incidentFixture({ id: 'inc-1' })],
  });
  server.respond({ method: 'GET', path: `${base}/alert-channels`, body: listOf([alertChannelFixture()]) });
  server.respond({ method: 'GET', path: `${base}/status-pages`, body: listOf([]) });
  server.respond({ method: 'GET', path: `${base}/members`, body: listOf([]) });
  server.respond({ method: 'GET', path: `${base}/invitations`, body: listOf([]) });
  return server;
}

function publicPage() {
  return fakeFetch([
    { method: 'GET', path: '/api/v1/public/status-pages/demo-services', body: { data: publicStatusPageFixture() } },
    { method: 'GET', path: '/api/v1/meta', body: { data: metaFixture() } },
  ]);
}

const screens: readonly [string, string, () => ReturnType<typeof fakeFetch>, string][] = [
  ['the landing', '/', () => guest(), 'Your websites and APIs'],
  ['sign in', '/login', () => guest(), 'Sign in to Uply'],
  ['sign up', '/register', () => guest(), 'Create your account'],
  ['the dashboard', '/dashboard', workspace, 'Dashboard'],
  ['the monitors', '/monitors', workspace, 'Monitors'],
  ['the incidents', '/incidents', workspace, 'Incidents'],
  ['the alert channels', '/alert-channels', workspace, 'Alert channels'],
  ['the status pages', '/status-pages', workspace, 'Status pages'],
  ['the members', '/settings/members', workspace, 'Settings'],
  ['a public status page', '/status/demo-services', publicPage, 'Demo services'],
];

describe('accessible names', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it.each(screens)('names every control of %s', async (_, path, server, heading) => {
    renderApp({ path, server: server() });

    await screen.findByRole('heading', { level: 1, name: new RegExp(heading) });
    await new Promise((resolve) => setTimeout(resolve, 50));

    const unnamed = roles.flatMap((role) => screen.queryAllByRole(role, { name: '' }).map((element) => `${role} ${element.outerHTML.slice(0, 120)}`));
    expect(unnamed).toEqual([]);
  });
});
