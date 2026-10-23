import { screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { MonitorFormDialog } from '@/features/monitors/components/MonitorFormDialog';
import type { Monitor } from '@/shared/api/resources';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { metaFixture, monitorFixture, organizationId } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

const base = `/api/v1/organizations/${organizationId}/monitors`;
const monitoring = metaFixture().monitoring;

function openDialog(monitor: Monitor | null) {
  const server = fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]);
  const onSaved = vi.fn(() => Promise.resolve());
  const onOpenChange = vi.fn();
  const page = renderPage(
    () => <MonitorFormDialog open organizationId={organizationId} monitor={monitor} monitoring={monitoring} onSaved={onSaved} onOpenChange={onOpenChange} />,
    { server },
  );
  return { ...page, onSaved, onOpenChange };
}

async function fillNew(page: ReturnType<typeof openDialog>) {
  await page.user.type(await screen.findByLabelText('Name'), 'Public API');
  await page.user.type(screen.getByLabelText('URL'), 'https://api.example.test/health');
}

describe('MonitorFormDialog', () => {
  it('adds a monitor with the defaults of meta', async () => {
    const page = openDialog(null);
    page.server.respond({ method: 'POST', path: base, status: 201, body: { data: monitorFixture() } });

    await fillNew(page);
    await page.user.selectOptions(screen.getByLabelText('Check interval'), 'Every 5 minutes');
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('POST', base)[0]?.body).toEqual({
      name: 'Public API',
      url: 'https://api.example.test/health',
      method: 'get',
      interval_seconds: 300,
      timeout_seconds: 10,
      accepted_status_codes: ['200-299'],
      follow_redirects: true,
      failure_threshold: 2,
      recovery_threshold: 2,
    });
    expect(page.onSaved).toHaveBeenCalledWith(monitorFixture());
  });

  it('offers the methods of meta', async () => {
    openDialog(null);

    const method = await screen.findByLabelText('Method');
    expect([...method.querySelectorAll('option')].map((option) => option.textContent)).toEqual(['GET', 'HEAD', 'POST', 'PUT', 'PATCH']);
  });

  it('checks the fields before sending', async () => {
    const page = openDialog(null);
    await page.user.type(await screen.findByLabelText('URL'), 'ftp://files.example.test');
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    expect(await screen.findByText('Enter an http or https address.')).toBeInTheDocument();
    expect(screen.getByText('This field is required.')).toBeInTheDocument();
    expect(page.server.requestsTo('POST', base)).toEqual([]);
  });

  it('shows the field errors of a 422 under their fields', async () => {
    const page = openDialog(null);
    page.server.respond({
      method: 'POST',
      path: base,
      status: 422,
      body: { message: 'Invalid.', errors: { url: ['This address points to a blocked network.'], 'accepted_status_codes.0': ['The range is not valid.'] } },
    });

    await fillNew(page);
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    expect(await screen.findByText('This address points to a blocked network.')).toBeInTheDocument();
    expect(screen.getByText('The range is not valid.')).toBeInTheDocument();
    expect(page.onOpenChange).not.toHaveBeenCalled();
  });

  it('says when the organization has reached its monitor limit', async () => {
    const page = openDialog(null);
    page.server.respond({ method: 'POST', path: base, status: 409, body: { message: 'Limit.', code: 'monitor_limit_reached' } });

    await fillNew(page);
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('This organization has reached its monitor limit.');
    expect(page.onSaved).not.toHaveBeenCalled();
  });

  it('patches only the changed fields of a monitor', async () => {
    const monitor = monitorFixture({ id: 'mon-1' });
    const page = openDialog(monitor);
    page.server.respond({ method: 'PATCH', path: `${base}/mon-1`, body: { data: { ...monitor, name: 'Renamed API' } } });

    const name = await screen.findByLabelText('Name');
    await page.user.clear(name);
    await page.user.type(name, 'Renamed API');
    await page.user.click(screen.getByRole('switch', { name: 'Follow redirects' }));
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)[0]?.body).toEqual({ name: 'Renamed API', follow_redirects: false });
  });

  it('keeps the advanced section closed on create and sends its thresholds', async () => {
    const page = openDialog(null);
    page.server.respond({ method: 'POST', path: base, status: 201, body: { data: monitorFixture() } });

    const toggle = await screen.findByRole('button', { name: /Advanced/ });
    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('spinbutton', { name: 'Failures before down' })).toBeNull();
    await fillNew(page);
    await page.user.click(toggle);
    const failures = screen.getByRole('spinbutton', { name: 'Failures before down' });
    await page.user.clear(failures);
    await page.user.type(failures, '1');
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('POST', base)[0]?.body).toMatchObject({ failure_threshold: 1, recovery_threshold: 2 });
  });

  it('opens the advanced section on a threshold off the default and patches the change', async () => {
    const monitor = monitorFixture({ id: 'mon-1', recovery_threshold: 3 });
    const page = openDialog(monitor);
    page.server.respond({ method: 'PATCH', path: `${base}/mon-1`, body: { data: { ...monitor, recovery_threshold: 5 } } });

    expect(await screen.findByRole('button', { name: /Advanced/ })).toHaveAttribute('aria-expanded', 'true');
    const recoveries = screen.getByRole('spinbutton', { name: 'Successes before up' });
    expect(recoveries).toHaveValue(3);
    await page.user.clear(recoveries);
    await page.user.type(recoveries, '5');
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)[0]?.body).toEqual({ recovery_threshold: 5 });
  });

  it('opens the advanced section on one of its errors', async () => {
    const page = openDialog(monitorFixture({ id: 'mon-1' }));
    page.server.respond({ method: 'PATCH', path: `${base}/mon-1`, status: 422, body: { message: 'Invalid.', errors: { failure_threshold: ['The threshold is too high.'] } } });

    const toggle = await screen.findByRole('button', { name: /Advanced/ });
    await page.user.click(toggle);
    const failures = screen.getByRole('spinbutton', { name: 'Failures before down' });
    await page.user.clear(failures);
    await page.user.type(failures, '0');
    await page.user.click(toggle);
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    expect(await screen.findByText('Use a whole number from 1 to 10.')).toBeVisible();
    expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.user.clear(failures);
    await page.user.type(failures, '9');
    await page.user.click(toggle);
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    expect(await screen.findByText('The threshold is too high.')).toBeVisible();
    expect(toggle).toHaveAttribute('aria-expanded', 'true');
  });

  it('adds a keyword with its rule, and hides it for HEAD', async () => {
    const page = openDialog(null);
    page.server.respond({ method: 'POST', path: base, status: 201, body: { data: monitorFixture() } });

    await fillNew(page);
    await page.user.click(screen.getByRole('button', { name: /Advanced/ }));
    await page.user.type(screen.getByRole('textbox', { name: 'Keyword' }), ' Maintenance ');
    const rule = screen.getByRole('combobox', { name: 'Keyword rule' });
    expect([...rule.querySelectorAll('option')].map((option) => option.textContent)).toEqual(['Must contain', 'Must not contain']);
    await page.user.selectOptions(rule, 'Must not contain');
    await page.user.selectOptions(screen.getByLabelText('Method'), 'HEAD');
    expect(screen.queryByRole('textbox', { name: 'Keyword' })).toBeNull();
    await page.user.selectOptions(screen.getByLabelText('Method'), 'GET');
    expect(screen.getByRole('textbox', { name: 'Keyword' })).toHaveValue(' Maintenance ');
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('POST', base)[0]?.body).toMatchObject({ method: 'get', keyword: 'Maintenance', keyword_match: 'absent' });
  });

  it('shows the keyword of a monitor and clears it', async () => {
    const monitor = monitorFixture({ id: 'mon-1', keyword: 'Welcome', keyword_match: 'present' });
    const page = openDialog(monitor);
    page.server.respond({ method: 'PATCH', path: `${base}/mon-1`, body: { data: { ...monitor, keyword: null } } });

    const keyword = await screen.findByRole('textbox', { name: 'Keyword' });
    expect(keyword).toHaveValue('Welcome');
    await page.user.clear(keyword);
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)[0]?.body).toEqual({ keyword: null });
  });

  it('adds and removes header rows, and sends the rows left', async () => {
    const page = openDialog(null);
    page.server.respond({ method: 'POST', path: base, status: 201, body: { data: monitorFixture() } });

    await fillNew(page);
    await page.user.click(screen.getByRole('button', { name: /Advanced/ }));
    await page.user.click(screen.getByRole('button', { name: 'Add header' }));
    await page.user.click(screen.getByRole('button', { name: 'Add header' }));
    const first = screen.getByRole('group', { name: 'Header 1' });
    await page.user.type(within(first).getByLabelText('Header name'), 'X-Removed');
    await page.user.type(within(first).getByLabelText('Header value'), 'gone');
    const second = screen.getByRole('group', { name: 'Header 2' });
    await page.user.type(within(second).getByLabelText('Header name'), 'Accept');
    await page.user.type(within(second).getByLabelText('Header value'), ' application/json ');
    await page.user.click(screen.getByRole('button', { name: 'Remove header 1' }));
    expect(screen.queryByRole('group', { name: 'Header 2' })).toBeNull();
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('POST', base)[0]?.body).toMatchObject({ request_headers: [{ name: 'Accept', value: 'application/json' }] });
  });

  it('stops adding rows at the limit of meta', async () => {
    const server = fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]);
    const limited = { ...monitoring, limits: { ...monitoring.limits, max_request_headers: 1 } };
    const page = renderPage(() => <MonitorFormDialog open organizationId={organizationId} monitor={null} monitoring={limited} onSaved={vi.fn()} onOpenChange={vi.fn()} />, { server });

    await page.user.click(await screen.findByRole('button', { name: /Advanced/ }));
    await page.user.click(screen.getByRole('button', { name: 'Add header' }));

    expect(screen.getByRole('button', { name: 'Add header' })).toBeDisabled();
    expect(screen.getByText('Up to 1 headers. Values are stored encrypted and never shown again.')).toBeInTheDocument();
  });

  it('checks the header rows before sending', async () => {
    const page = openDialog(null);

    await fillNew(page);
    await page.user.click(screen.getByRole('button', { name: /Advanced/ }));
    await page.user.click(screen.getByRole('button', { name: 'Add header' }));
    await page.user.click(screen.getByRole('button', { name: 'Add header' }));
    const first = screen.getByRole('group', { name: 'Header 1' });
    await page.user.type(within(first).getByLabelText('Header name'), 'Host');
    await page.user.type(within(first).getByLabelText('Header value'), 'example.test');
    const second = screen.getByRole('group', { name: 'Header 2' });
    await page.user.type(within(second).getByLabelText('Header name'), 'X Trace');
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    expect(await within(first).findByText('Uply sets this header itself.')).toBeInTheDocument();
    expect(within(second).getByText("Use letters, digits and ! # $ % & ' * + - . ^ _ ` | ~ only, without spaces.")).toBeInTheDocument();
    expect(within(second).getByText('This field is required.')).toBeInTheDocument();
    expect(page.server.requestsTo('POST', base)).toEqual([]);
  });

  it('lists the stored header names and replaces them all', async () => {
    const monitor = monitorFixture({ id: 'mon-1', request_header_names: ['Authorization', 'X-Trace'] });
    const page = openDialog(monitor);
    page.server.respond({ method: 'PATCH', path: `${base}/mon-1`, body: { data: { ...monitor, request_header_names: ['Authorization'] } } });

    const stored = await screen.findByRole('list', { name: 'Stored headers' });
    expect(within(stored).getAllByRole('listitem').map((item) => item.textContent)).toEqual(['Authorization', 'X-Trace']);
    expect(screen.queryByRole('group', { name: 'Header 1' })).toBeNull();
    await page.user.click(screen.getByRole('button', { name: 'Replace headers' }));
    const first = screen.getByRole('group', { name: 'Header 1' });
    expect(within(first).getByLabelText('Header name')).toHaveValue('Authorization');
    expect(within(first).getByLabelText('Header value')).toHaveValue('');
    await page.user.type(within(first).getByLabelText('Header value'), 'Bearer next');
    await page.user.click(screen.getByRole('button', { name: 'Remove header 2' }));
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)[0]?.body).toEqual({ request_headers: [{ name: 'Authorization', value: 'Bearer next' }] });
  });

  it('clears the stored headers with an empty list, and keeps them without Replace', async () => {
    const monitor = monitorFixture({ id: 'mon-1', request_header_names: ['Authorization'] });
    const page = openDialog(monitor);
    page.server.respond({ method: 'PATCH', path: `${base}/mon-1`, body: { data: { ...monitor, request_header_names: [] } } });

    await page.user.click(await screen.findByRole('button', { name: 'Replace headers' }));
    await page.user.click(screen.getByRole('button', { name: 'Remove header 1' }));
    expect(screen.getByText('Type every value again. Saving an empty list removes the headers.')).toBeInTheDocument();
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)[0]?.body).toEqual({ request_headers: [] });
  });

  it('shows the errors of the API on their header row', async () => {
    const page = openDialog(null);
    page.server.respond({
      method: 'POST',
      path: base,
      status: 422,
      body: { message: 'Invalid.', errors: { 'request_headers.1.name': ['The header name is refused.'], 'request_headers.0.value': ['The value is not valid.'] } },
    });

    await fillNew(page);
    await page.user.click(screen.getByRole('button', { name: /Advanced/ }));
    for (const [index, name] of ['X-One', 'X-Two'].entries()) {
      await page.user.click(screen.getByRole('button', { name: 'Add header' }));
      const row = screen.getByRole('group', { name: `Header ${String(index + 1)}` });
      await page.user.type(within(row).getByLabelText('Header name'), name);
      await page.user.type(within(row).getByLabelText('Header value'), 'value');
    }
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    expect(await within(screen.getByRole('group', { name: 'Header 2' })).findByText('The header name is refused.')).toBeInTheDocument();
    expect(within(screen.getByRole('group', { name: 'Header 1' })).getByText('The value is not valid.')).toBeInTheDocument();
    expect(page.onOpenChange).not.toHaveBeenCalled();
  });

  it('sends the body as typed for a body method only', async () => {
    const page = openDialog(null);
    page.server.respond({ method: 'POST', path: base, status: 201, body: { data: monitorFixture({ method: 'post', has_request_body: true }) } });

    await fillNew(page);
    await page.user.click(screen.getByRole('button', { name: /Advanced/ }));
    expect(screen.queryByRole('textbox', { name: 'Request body' })).toBeNull();
    await page.user.selectOptions(screen.getByLabelText('Method'), 'POST');
    const body = screen.getByRole('textbox', { name: 'Request body' });
    expect(body).toHaveAttribute('spellcheck', 'false');
    expect(body).toHaveAttribute('autocomplete', 'off');
    await page.user.type(body, ' {{"ping": true} ');
    await page.user.click(screen.getByRole('button', { name: 'Add monitor' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('POST', base)[0]?.body).toMatchObject({ method: 'post', request_body: ' {"ping": true} ' });
  });

  it('keeps a stored body until it is replaced, then clears it when left empty', async () => {
    const monitor = monitorFixture({ id: 'mon-1', method: 'put', has_request_body: true });
    const page = openDialog(monitor);
    page.server.respond({ method: 'PATCH', path: `${base}/mon-1`, body: { data: { ...monitor, has_request_body: false } } });

    expect(await screen.findByText('Body stored')).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: 'Request body' })).toBeNull();
    await page.user.click(screen.getByRole('button', { name: 'Replace body' }));
    expect(screen.getByRole('textbox', { name: 'Request body' })).toHaveValue('');
    expect(screen.getByText('Leave it empty to remove the body.')).toBeInTheDocument();
    await page.user.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.onOpenChange).toHaveBeenCalledWith(false);
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)[0]?.body).toEqual({ request_body: null });
  });

  it('sends nothing about a stored body that was not replaced', async () => {
    const monitor = monitorFixture({ id: 'mon-1', method: 'post', has_request_body: true, request_header_names: ['Authorization'] });
    const page = openDialog(monitor);

    await page.user.click(await screen.findByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.onSaved).toHaveBeenCalledWith(monitor);
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)).toEqual([]);
  });

  it('sends nothing when the monitor did not change', async () => {
    const monitor = monitorFixture({ id: 'mon-1' });
    const page = openDialog(monitor);

    await page.user.click(await screen.findByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.onSaved).toHaveBeenCalledWith(monitor);
    });
    expect(page.server.requestsTo('PATCH', `${base}/mon-1`)).toEqual([]);
  });
});
