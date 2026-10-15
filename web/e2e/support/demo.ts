import { type Browser, expect } from '@playwright/test';
import { apiCall, baseUrl, demo, expectOk, signInByApi } from './session';

export const demoStatusPageSlug = 'demo-services';

interface Listed {
  id: string;
  slug?: string;
  is_published?: boolean;
}

function listOf(body: unknown): Listed[] {
  if (typeof body !== 'object' || body === null || !('data' in body) || !Array.isArray(body.data)) {
    return [];
  }
  return body.data.flatMap((item: unknown) =>
    typeof item === 'object' && item !== null && 'id' in item && typeof item.id === 'string'
      ? [
          {
            id: item.id,
            ...('slug' in item && typeof item.slug === 'string' ? { slug: item.slug } : {}),
            ...('is_published' in item && typeof item.is_published === 'boolean' ? { is_published: item.is_published } : {}),
          },
        ]
      : [],
  );
}

export async function ensureDemoStatusPage(browser: Browser): Promise<void> {
  const context = await browser.newContext({ baseURL: baseUrl() });
  try {
    const published = await context.request.get(`/api/v1/public/status-pages/${demoStatusPageSlug}`, { headers: { Accept: 'application/json' } });
    if (published.ok()) {
      return;
    }
    await signInByApi(context, demo.owner);
    const organizationId = listOf(await expectOk(await apiCall(context, 'GET', '/organizations')))[0]?.id;
    expect(organizationId, 'the demo user has an organization').toBeDefined();
    const base = `/organizations/${organizationId ?? ''}`;
    const monitors = listOf(await expectOk(await apiCall(context, 'GET', `${base}/monitors?per_page=100`)));
    const existing = listOf(await expectOk(await apiCall(context, 'GET', `${base}/status-pages`))).find((page) => page.slug === demoStatusPageSlug);
    const body = { title: 'Demo services', description: 'Live state of the demo services.', is_published: true, monitors: monitors.slice(0, 3).map((monitor) => ({ monitor_id: monitor.id })) };
    await expectOk(
      existing === undefined
        ? await apiCall(context, 'POST', `${base}/status-pages`, { ...body, slug: demoStatusPageSlug })
        : await apiCall(context, 'PATCH', `${base}/status-pages/${existing.id}`, { is_published: true }),
    );
  } finally {
    await context.close();
  }
}
