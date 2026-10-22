import { screen, waitFor } from '@testing-library/react';
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
