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
    });
    expect(page.onSaved).toHaveBeenCalledWith(monitorFixture());
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
