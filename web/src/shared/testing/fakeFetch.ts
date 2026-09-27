export interface FakeRoute {
  method: string;
  path: string;
  status?: number;
  body?: unknown;
  headers?: Record<string, string>;
}

export interface RecordedRequest {
  method: string;
  path: string;
  search: string;
  headers: Headers;
  body: unknown;
}

export interface FakeFetch {
  fetch: typeof globalThis.fetch;
  requests: RecordedRequest[];
  respond(route: FakeRoute): void;
  requestsTo(method: string, path: string): RecordedRequest[];
}

export const testOrigin = 'http://localhost:5173';

async function readBody(request: Request): Promise<unknown> {
  const text = await request.text();
  if (text === '') {
    return null;
  }
  try {
    return JSON.parse(text) as unknown;
  } catch {
    return text;
  }
}

export function fakeFetch(initialRoutes: FakeRoute[] = []): FakeFetch {
  const routes: FakeRoute[] = [...initialRoutes];
  const requests: RecordedRequest[] = [];

  const fetch: typeof globalThis.fetch = async (input, init) => {
    const request = input instanceof Request ? input : new Request(input, init);
    const url = new URL(request.url);
    requests.push({
      method: request.method,
      path: url.pathname,
      search: url.search,
      headers: request.headers,
      body: await readBody(request.clone()),
    });
    const route = [...routes].reverse().find((candidate) => candidate.method === request.method && candidate.path === url.pathname);
    if (route === undefined) {
      return new Response(JSON.stringify({ message: 'Not found.' }), { status: 404, headers: { 'Content-Type': 'application/json' } });
    }
    const status = route.status ?? 200;
    const hasBody = route.body !== undefined && status !== 204;
    return new Response(hasBody ? JSON.stringify(route.body) : null, {
      status,
      headers: { 'Content-Type': 'application/json', ...route.headers },
    });
  };

  return {
    fetch,
    requests,
    respond(route) {
      routes.push(route);
    },
    requestsTo(method, path) {
      return requests.filter((request) => request.method === method && request.path === path);
    },
  };
}
