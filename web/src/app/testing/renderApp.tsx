import { createMemoryHistory } from '@tanstack/react-router';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppProviders } from '@/app/App';
import { createAppServices } from '@/app/services';
import { type FakeFetch, fakeFetch, testOrigin } from '@/shared/testing/fakeFetch';
import { meFixture, organizationId } from '@/shared/testing/fixtures';
import type { Me } from '@/shared/api/resources';

export interface RenderAppOptions {
  path: string;
  server?: FakeFetch;
}

export function renderApp({ path, server = fakeFetch() }: RenderAppOptions) {
  const services = createAppServices({
    origin: testOrigin,
    fetch: server.fetch,
    readCookies: () => 'XSRF-TOKEN=test-token',
    history: createMemoryHistory({ initialEntries: [path] }),
    retries: 0,
  });
  const view = render(<AppProviders services={services} />);
  return { ...services, server, view, user: userEvent.setup() };
}

export function signedIn(server: FakeFetch, me: Me = meFixture()): FakeFetch {
  server.respond({ method: 'GET', path: '/api/v1/me', body: { data: me } });
  server.respond({
    method: 'GET',
    path: '/api/v1/organizations',
    body: {
      data: me.memberships.flatMap((membership) =>
        membership.organization === null
          ? []
          : [{ id: membership.organization.id, name: membership.organization.name, slug: membership.organization.slug, role: membership.role, created_at: null }],
      ),
      links: { first: null, last: null, prev: null, next: null },
      meta: { current_page: 1, from: 1, last_page: 1, links: [], path: null, per_page: 100, to: me.memberships.length, total: me.memberships.length },
    },
  });
  return server;
}

export function guest(server: FakeFetch = fakeFetch()): FakeFetch {
  server.respond({ method: 'GET', path: '/api/v1/me', status: 401, body: { message: 'Unauthenticated.' } });
  return server;
}

export { organizationId };
