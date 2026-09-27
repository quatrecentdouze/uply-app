import createClient, { type Client, type Middleware } from 'openapi-fetch';
import type { ApiPaths } from '@/shared/api/contract';
import { ApiError, failureFromResponse } from '@/shared/api/errors';

export type ApiClient = Client<ApiPaths>;

export interface ApiClientOptions {
  origin: string;
  fetch?: typeof globalThis.fetch;
  readCookies?: () => string;
}

const apiPrefix = '/api';
const csrfCookiePath = '/sanctum/csrf-cookie';
const xsrfCookie = 'XSRF-TOKEN';
const xsrfHeader = 'X-XSRF-TOKEN';
const readMethods = new Set(['GET', 'HEAD', 'OPTIONS']);

export function cookieValue(cookies: string, name: string): string | null {
  for (const part of cookies.split(';')) {
    const separator = part.indexOf('=');
    if (separator !== -1 && part.slice(0, separator).trim() === name) {
      return decodeURIComponent(part.slice(separator + 1).trim());
    }
  }
  return null;
}

function csrfMiddleware(origin: string, send: typeof globalThis.fetch, readCookies: () => string): Middleware {
  let cookieRequest: Promise<void> | null = null;

  const ensureCookie = (): Promise<void> => {
    cookieRequest ??= send(new Request(`${origin}${csrfCookiePath}`, { credentials: 'include', headers: { Accept: 'application/json' } })).then(
      (response) => {
        if (!response.ok) {
          cookieRequest = null;
          throw new ApiError(failureFromResponse(response.status, null, response.headers));
        }
      },
      () => {
        cookieRequest = null;
        throw new ApiError({ kind: 'network' });
      },
    );
    return cookieRequest;
  };

  return {
    async onRequest({ request }) {
      request.headers.set('Accept', 'application/json');
      if (readMethods.has(request.method)) {
        return request;
      }
      await ensureCookie();
      const token = cookieValue(readCookies(), xsrfCookie);
      if (token !== null) {
        request.headers.set(xsrfHeader, token);
      }
      return request;
    },
    onResponse({ response }) {
      if (response.status === 419) {
        cookieRequest = null;
      }
      return response;
    },
  };
}

export function createApiClient(options: ApiClientOptions): ApiClient {
  const send = options.fetch ?? ((input, init) => globalThis.fetch(input, init));
  const readCookies = options.readCookies ?? (() => document.cookie);
  const client = createClient<ApiPaths>({
    baseUrl: `${options.origin}${apiPrefix}`,
    credentials: 'include',
    fetch: send,
  });
  client.use(csrfMiddleware(options.origin, send, readCookies));
  return client;
}

interface ApiResult<T> {
  data?: T;
  error?: unknown;
  response: Response;
}

export async function expectData<T>(pending: Promise<ApiResult<T>>): Promise<T> {
  let result: ApiResult<T>;
  try {
    result = await pending;
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }
    throw new ApiError({ kind: 'network' });
  }
  if (!result.response.ok || result.data === undefined) {
    throw new ApiError(failureFromResponse(result.response.status, result.error, result.response.headers));
  }
  return result.data;
}

export async function expectSuccess(pending: Promise<ApiResult<unknown>>): Promise<Response> {
  let result: ApiResult<unknown>;
  try {
    result = await pending;
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }
    throw new ApiError({ kind: 'network' });
  }
  if (!result.response.ok) {
    throw new ApiError(failureFromResponse(result.response.status, result.error, result.response.headers));
  }
  return result.response;
}
