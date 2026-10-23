import { screen, waitFor, within } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { MonitorFormDialog } from '@/features/monitors/components/MonitorFormDialog';
import type { Monitor } from '@/shared/api/resources';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { metaFixture, monitorFixture, organizationId } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

const base = `/api/v1/organizations/${organizationId}/monitors`;
const monitoring = metaFixture().monitoring;
const headerValue = 'Bearer s3cr3t-header-value';
const requestBody = '{"token":"s3cr3t-body"}';

function Harness({ monitor }: { monitor: Monitor | null }) {
  const [open, setOpen] = useState(true);
  return (
    <>
      <button
        type="button"
        onClick={() => {
          setOpen(true);
        }}
      >
        Reopen
      </button>
      <MonitorFormDialog open={open} onOpenChange={setOpen} organizationId={organizationId} monitor={monitor} monitoring={monitoring} onSaved={() => Promise.resolve()} />
    </>
  );
}

function renderHarness(monitor: Monitor | null) {
  const server = fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]);
  return renderPage(() => <Harness monitor={monitor} />, { server });
}

function leftovers(page: ReturnType<typeof renderHarness>): string {
  const fieldValues = [...document.querySelectorAll('input, textarea')].map((field) => (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement ? field.value : ''));
  const caches = [page.queryClient.getQueryCache().getAll().map((query) => query.state), page.queryClient.getMutationCache().getAll().map((mutation) => mutation.state)];
  return JSON.stringify([document.body.innerHTML, fieldValues, caches]);
}

describe('monitor form secrets', () => {
  it('leaves no header value and no body in the cache or the page once a monitor is created', async () => {
    const page = renderHarness(null);
    page.server.respond({ method: 'POST', path: base, status: 201, body: { data: monitorFixture({ method: 'post', request_header_names: ['Authorization'], has_request_body: true }) } });

    await page.user.type(await screen.findByLabelText('Name'), 'Private API');
    await page.user.type(screen.getByLabelText('URL'), 'https://api.example.test/private');
    await page.user.selectOptions(screen.getByLabelText('Method'), 'POST');
    await page.user.click(screen.getByRole('button', { name: /Advanced/ }));
    await page.user.click(screen.getByRole('button', { name: 'Add header' }));
    const row = screen.getByRole('group', { name: 'Header 1' });
    await page.user.type(within(row).getByLabelText('Header name'), 'Authorization');
    await page.user.type(within(row).getByLabelText('Header value'), headerValue);
    await page.user.type(screen.getByRole('textbox', { name: 'Request body' }), requestBody.replaceAll('{', '{{'));
    expect(leftovers(page)).toContain('s3cr3t-header-value');
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
    expect(page.server.requestsTo('POST', base)[0]?.body).toMatchObject({ request_headers: [{ name: 'Authorization', value: headerValue }], request_body: requestBody });
    expect(leftovers(page)).not.toMatch(/s3cr3t/);
  });

  it('leaves nothing behind once replaced headers and body are saved', async () => {
    const monitor = monitorFixture({ id: 'mon-1', method: 'patch', request_header_names: ['Authorization'], has_request_body: true });
    const page = renderHarness(monitor);
    page.server.respond({ method: 'PATCH', path: `${base}/mon-1`, body: { data: monitor } });

    await page.user.click(await screen.findByRole('button', { name: 'Replace headers' }));
    await page.user.type(within(screen.getByRole('group', { name: 'Header 1' })).getByLabelText('Header value'), headerValue);
    await page.user.click(screen.getByRole('button', { name: 'Replace body' }));
    await page.user.type(screen.getByRole('textbox', { name: 'Request body' }), requestBody.replaceAll('{', '{{'));
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)[0]?.body).toEqual({ request_headers: [{ name: 'Authorization', value: headerValue }], request_body: requestBody });
    expect(leftovers(page)).not.toMatch(/s3cr3t/);
  });

  it('drops the typed values when the dialog is closed without saving', async () => {
    const page = renderHarness(null);

    await page.user.click(await screen.findByRole('button', { name: /Advanced/ }));
    await page.user.click(screen.getByRole('button', { name: 'Add header' }));
    await page.user.type(within(screen.getByRole('group', { name: 'Header 1' })).getByLabelText('Header value'), headerValue);
    await page.user.click(screen.getByRole('button', { name: 'Cancel' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
    expect(leftovers(page)).not.toMatch(/s3cr3t/);

    await page.user.click(screen.getByRole('button', { name: 'Reopen' }));
    await page.user.click(await screen.findByRole('button', { name: /Advanced/ }));
    expect(screen.queryByRole('group', { name: 'Header 1' })).toBeNull();
    expect(page.server.requests).toHaveLength(0);
  });

  it('names every control of the advanced section and keeps secrets out of autofill and spell check', async () => {
    const page = renderHarness(null);

    await page.user.selectOptions(await screen.findByLabelText('Method'), 'PUT');
    await page.user.click(screen.getByRole('button', { name: /Advanced/ }));
    await page.user.click(screen.getByRole('button', { name: 'Add header' }));

    for (const control of (['button', 'textbox', 'combobox', 'spinbutton'] as const).flatMap((role) => screen.getAllByRole(role))) {
      expect(control).toHaveAccessibleName();
    }
    for (const field of [within(screen.getByRole('group', { name: 'Header 1' })).getByLabelText('Header value'), screen.getByRole('textbox', { name: 'Request body' })]) {
      expect(field).toHaveAttribute('autocomplete', 'off');
      expect(field).toHaveAttribute('spellcheck', 'false');
      expect(field).not.toHaveAttribute('type', 'password');
    }
  });
});
