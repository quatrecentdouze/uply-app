import type { APIRequestContext } from '@playwright/test';

const mailpitUrl = process.env.MAILPIT_URL ?? 'http://127.0.0.1:8025';

interface MailpitSummary {
  ID: string;
  To: { Address: string }[];
}

interface MailpitMessage {
  Text: string;
}

function isSummaryList(value: unknown): value is { messages: MailpitSummary[] } {
  return typeof value === 'object' && value !== null && 'messages' in value && Array.isArray(value.messages);
}

function isMessage(value: unknown): value is MailpitMessage {
  return typeof value === 'object' && value !== null && 'Text' in value && typeof value.Text === 'string';
}

async function messageIds(request: APIRequestContext, address: string): Promise<string[]> {
  const response = await request.get(`${mailpitUrl}/api/v1/search`, { params: { query: `to:"${address}"` } });
  const body: unknown = await response.json();
  return isSummaryList(body) ? body.messages.map((message) => message.ID) : [];
}

async function findLink(request: APIRequestContext, address: string, pattern: RegExp): Promise<string | null> {
  for (const id of await messageIds(request, address)) {
    const body: unknown = await (await request.get(`${mailpitUrl}/api/v1/message/${id}`)).json();
    const match = isMessage(body) ? pattern.exec(body.Text) : null;
    if (match !== null) {
      return match[0];
    }
  }
  return null;
}

const waitMilliseconds = 60_000;
const pollMilliseconds = 1_000;

export async function waitForLink(request: APIRequestContext, address: string, pattern: RegExp): Promise<string> {
  const deadline = Date.now() + waitMilliseconds;
  while (Date.now() < deadline) {
    const link = await findLink(request, address, pattern);
    if (link !== null) {
      return link;
    }
    await new Promise((resolve) => setTimeout(resolve, pollMilliseconds));
  }
  throw new Error(`no mail for ${address} matched ${pattern.source} within ${String(waitMilliseconds)} ms`);
}

export async function deleteMessagesTo(request: APIRequestContext, address: string): Promise<void> {
  const ids = await messageIds(request, address);
  if (ids.length > 0) {
    await request.delete(`${mailpitUrl}/api/v1/messages`, { data: { IDs: ids } });
  }
}
