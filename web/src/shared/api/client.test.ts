import { describe, expect, it } from 'vitest';
import { cookieValue, createApiClient, expectData, expectSuccess } from '@/shared/api/client';
import { ApiError, failureOf } from '@/shared/api/errors';
import { fakeFetch, testOrigin } from '@/shared/testing/fakeFetch';

const me = {
  id: '01jbxuser',
  name: 'Demo',
  email: 'demo@uply.test',
  email_verified_at: '2026-10-29T00:00:00Z',
  two_factor_enabled: false,
  created_at: '2026-10-29T00:00:00Z',
  memberships: [],
};

function clientWith(server: ReturnType<typeof fakeFetch>, cookies = 'XSRF-TOKEN=abc%3D%3D; laravel_session=xyz') {
  return createApiClient({ origin: testOrigin, fetch: server.fetch, readCookies: () => cookies });
}

describe('cookieValue', () => {
  it('reads and decodes one cookie', () => {
    expect(cookieValue('a=1; XSRF-TOKEN=abc%3D%3D', 'XSRF-TOKEN')).toBe('abc==');
    expect(cookieValue('a=1', 'XSRF-TOKEN')).toBeNull();
  });
});

describe('createApiClient', () => {
  it('reads under /api with the JSON accept header and no csrf call', async () => {
    const server = fakeFetch([{ method: 'GET', path: '/api/v1/me', body: { data: me } }]);
    const client = clientWith(server);

    const answer = await expectData(client.GET('/v1/me'));

    expect(answer.data.email).toBe('demo@uply.test');
    expect(server.requests).toHaveLength(1);
    expect(server.requests[0]?.headers.get('Accept')).toBe('application/json');
    expect(server.requests[0]?.headers.get('X-XSRF-TOKEN')).toBeNull();
  });

  it('fetches the csrf cookie once before the first write and sends the XSRF header', async () => {
    const server = fakeFetch([
      { method: 'GET', path: '/sanctum/csrf-cookie', status: 204 },
      { method: 'POST', path: '/api/v1/auth/logout', status: 204 },
    ]);
    const client = clientWith(server);

    await expectSuccess(client.POST('/v1/auth/logout'));
    await expectSuccess(client.POST('/v1/auth/logout'));

    expect(server.requests.map((request) => `${request.method} ${request.path}`)).toEqual([
      'GET /sanctum/csrf-cookie',
      'POST /api/v1/auth/logout',
      'POST /api/v1/auth/logout',
    ]);
    expect(server.requestsTo('POST', '/api/v1/auth/logout')[1]?.headers.get('X-XSRF-TOKEN')).toBe('abc==');
  });

  it('asks for a new csrf cookie after a 419', async () => {
    const server = fakeFetch([
      { method: 'GET', path: '/sanctum/csrf-cookie', status: 204 },
      { method: 'POST', path: '/api/v1/auth/logout', status: 419, body: { message: 'CSRF token mismatch.' } },
    ]);
    const client = clientWith(server);

    await expect(expectSuccess(client.POST('/v1/auth/logout'))).rejects.toBeInstanceOf(ApiError);
    await expect(expectSuccess(client.POST('/v1/auth/logout'))).rejects.toBeInstanceOf(ApiError);

    expect(server.requestsTo('GET', '/sanctum/csrf-cookie')).toHaveLength(2);
  });

  it('turns a domain error into its code', async () => {
    const server = fakeFetch([
      { method: 'GET', path: '/sanctum/csrf-cookie', status: 204 },
      { method: 'POST', path: '/api/v1/invitations/token-1/accept', status: 409, body: { message: 'Already a member.', code: 'already_member' } },
    ]);
    const client = clientWith(server);

    const failure = await expectData(client.POST('/v1/invitations/{token}/accept', { params: { path: { token: 'token-1' } } })).catch(failureOf);

    expect(failure).toEqual({ kind: 'domain', status: 409, code: 'already_member', message: 'Already a member.' });
  });

  it('turns a thrown fetch into a network failure', async () => {
    const client = createApiClient({
      origin: testOrigin,
      fetch: () => Promise.reject(new TypeError('offline')),
      readCookies: () => '',
    });

    const failure = await expectData(client.GET('/v1/me')).catch(failureOf);

    expect(failure).toEqual({ kind: 'network' });
  });
});
