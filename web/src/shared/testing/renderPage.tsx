import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createMemoryHistory, createRootRoute, createRoute, createRouter, Outlet, RouterProvider } from '@tanstack/react-router';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { I18nextProvider } from 'react-i18next';
import { ApiClientContext } from '@/shared/api/apiClientContext';
import { createApiClient } from '@/shared/api/client';
import { createI18n } from '@/shared/i18n/i18n';
import { type FakeFetch, fakeFetch, testOrigin } from '@/shared/testing/fakeFetch';

export interface RenderPageOptions {
  server?: FakeFetch;
  path?: string;
  queryClient?: QueryClient;
}

export function testQueryClient(): QueryClient {
  return new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
}

export function renderPage(page: () => ReactNode, options: RenderPageOptions = {}) {
  const server = options.server ?? fakeFetch();
  const queryClient = options.queryClient ?? testQueryClient();
  const client = createApiClient({ origin: testOrigin, fetch: server.fetch, readCookies: () => 'XSRF-TOKEN=test-token' });
  const rootRoute = createRootRoute({ component: Outlet });
  const pageRoute = createRoute({ getParentRoute: () => rootRoute, path: '$', component: page });
  const router = createRouter({
    routeTree: rootRoute.addChildren([pageRoute]),
    history: createMemoryHistory({ initialEntries: [options.path ?? '/'] }),
  });
  const view = render(
    <I18nextProvider i18n={createI18n()}>
      <QueryClientProvider client={queryClient}>
        <ApiClientContext value={client}>
          <RouterProvider router={router} />
        </ApiClientContext>
      </QueryClientProvider>
    </I18nextProvider>,
  );
  return { server, queryClient, router, view, user: userEvent.setup() };
}
