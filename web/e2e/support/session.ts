import { randomBytes, randomUUID } from 'node:crypto';
import { type APIResponse, type BrowserContext, expect } from '@playwright/test';
import { deleteMessagesTo, waitForLink } from './mailpit';

export const demo = {
  owner: { email: 'demo@uply.test', password: 'password' },
  viewer: { email: 'viewer@uply.test', password: 'password' },
} as const;

export interface Credentials {
  email: string;
  password: string;
}

export interface TestAccount extends Credentials {
  name: string;
  organizationId: string;
}

const verificationLink = /https?:\/\/[^\s"()[\]]+\/api\/v1\/auth\/email\/verify\/[^\s"()[\]]+/;

export function testCredentials(): Credentials & { name: string } {
  const tag = randomUUID().slice(0, 8);
  return { name: `E2E ${tag}`, email: `e2e-${tag}@uply.test`, password: `${randomBytes(12).toString('base64url')}Aa1` };
}

export function baseUrl(): string {
  return process.env.E2E_BASE_URL ?? 'http://localhost:5173';
}

async function xsrfHeaders(context: BrowserContext): Promise<Record<string, string>> {
  const baseURL = baseUrl();
  await context.request.get('/sanctum/csrf-cookie', { headers: { Referer: `${baseURL}/` } });
  const token = (await context.cookies(baseURL)).find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '';
  return { 'X-XSRF-TOKEN': decodeURIComponent(token), Accept: 'application/json', Referer: `${baseURL}/`, Origin: baseURL };
}

export async function apiCall(context: BrowserContext, method: 'POST' | 'PATCH' | 'DELETE' | 'GET', path: string, data?: unknown): Promise<APIResponse> {
  const headers = await xsrfHeaders(context);
  return context.request.fetch(`/api/v1${path}`, { method, headers, ...(data === undefined ? {} : { data }) });
}

export async function expectOk(response: APIResponse): Promise<unknown> {
  expect(response.ok(), `${response.url()} answered ${String(response.status())}: ${await response.text()}`).toBe(true);
  return response.status() === 204 ? null : ((await response.json()) as unknown);
}

export async function signInByApi(context: BrowserContext, credentials: Credentials): Promise<void> {
  await expectOk(await apiCall(context, 'POST', '/auth/login', credentials));
}

function dataId(body: unknown): string {
  if (typeof body === 'object' && body !== null && 'data' in body && typeof body.data === 'object' && body.data !== null && 'id' in body.data && typeof body.data.id === 'string') {
    return body.data.id;
  }
  throw new Error('the answer has no data.id');
}

export async function verifyEmail(context: BrowserContext, email: string): Promise<void> {
  const link = await waitForLink(context.request, email, verificationLink);
  const answer = await context.request.get(link, { headers: { Referer: `${baseUrl()}/` } });
  expect(answer.status()).toBeLessThan(400);
  await deleteMessagesTo(context.request, email);
}

export async function createAccount(context: BrowserContext): Promise<TestAccount> {
  const credentials = testCredentials();
  await expectOk(await apiCall(context, 'POST', '/auth/register', { ...credentials, password_confirmation: credentials.password }));
  await verifyEmail(context, credentials.email);
  const organizationId = dataId(await expectOk(await apiCall(context, 'POST', '/organizations', { name: `${credentials.name} team` })));
  return { ...credentials, organizationId };
}

export async function deleteAccount(context: BrowserContext, account: Credentials): Promise<void> {
  const answer = await apiCall(context, 'DELETE', '/me', { password: account.password });
  if (answer.status() === 401 || answer.status() === 419) {
    await signInByApi(context, account);
    await expectOk(await apiCall(context, 'DELETE', '/me', { password: account.password }));
  } else {
    await expectOk(answer);
  }
  await deleteMessagesTo(context.request, account.email);
}

export async function createMonitor(context: BrowserContext, organizationId: string, name: string): Promise<string> {
  const body = await expectOk(await apiCall(context, 'POST', `/organizations/${organizationId}/monitors`, { name, url: 'http://127.0.0.1:5173/', interval_seconds: 60 }));
  return dataId(body);
}
