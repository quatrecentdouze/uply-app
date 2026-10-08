import { screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AlertChannelsPage } from '@/features/alerting/pages/AlertChannelsPage';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { alertChannelFixture, listOf, metaFixture, organizationId } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

const base = `/api/v1/organizations/${organizationId}/alert-channels`;
const email = alertChannelFixture({ id: 'ch-mail', name: 'On-call email', recipients: ['ops@example.test'] });
const slack = alertChannelFixture({ id: 'ch-slack', type: 'slack', name: 'Ops Slack', recipients: null, webhook_hint: { host: 'hooks.slack.com', last_four: 'wxyz' } });

function openChannels(canManage = true) {
  const server = fakeFetch([
    { method: 'GET', path: '/sanctum/csrf-cookie', status: 204 },
    { method: 'GET', path: base, body: listOf([email, slack]) },
  ]);
  return renderPage(() => <AlertChannelsPage organizationId={organizationId} alerting={metaFixture().alerting} canManage={canManage} />, { server });
}

async function card(name: string) {
  return within(await screen.findByRole('region', { name }));
}

describe('AlertChannelsPage', () => {
  it('shows the recipients of an email channel and only the hint of a webhook', async () => {
    openChannels();

    expect((await card('On-call email')).getByText('ops@example.test')).toBeInTheDocument();
    expect((await card('Ops Slack')).getByText('Webhook on hooks.slack.com, ending in wxyz')).toBeInTheDocument();
  });

  it('creates an email channel', async () => {
    const page = openChannels();
    page.server.respond({ method: 'POST', path: base, status: 201, body: { data: email } });

    await page.user.click(await screen.findByRole('button', { name: 'Add channel' }));
    const dialog = within(await screen.findByRole('dialog', { name: 'Add an alert channel' }));
    await page.user.type(dialog.getByLabelText('Name'), 'Team mail');
    await page.user.type(dialog.getByLabelText('Recipients'), 'Ops@Example.test, dev@example.test');
    await page.user.click(dialog.getByRole('button', { name: 'Add channel' }));

    await waitFor(() => {
      expect(page.server.requestsTo('POST', base)[0]?.body).toEqual({ type: 'email', name: 'Team mail', is_enabled: true, recipients: ['ops@example.test', 'dev@example.test'] });
    });
  });

  it('creates a Discord channel and shows a refused webhook under its field', async () => {
    const page = openChannels();
    page.server.respond({ method: 'POST', path: base, status: 422, body: { message: 'Invalid.', errors: { webhook_url: ['This is not a Discord webhook address.'] } } });

    await page.user.click(await screen.findByRole('button', { name: 'Add channel' }));
    const dialog = within(await screen.findByRole('dialog'));
    await page.user.click(dialog.getByRole('radio', { name: 'Discord' }));
    await page.user.type(dialog.getByLabelText('Name'), 'Ops Discord');
    await page.user.type(dialog.getByLabelText('Webhook URL'), 'https://example.test/hook');
    await page.user.click(dialog.getByRole('button', { name: 'Add channel' }));

    expect(await dialog.findByText('This is not a Discord webhook address.')).toBeInTheDocument();
    expect(page.server.requestsTo('POST', base)[0]?.body).toEqual({ type: 'discord', name: 'Ops Discord', is_enabled: true, webhook_url: 'https://example.test/hook' });
  });

  it('edits a webhook channel without ever showing its address', async () => {
    const page = openChannels();
    page.server.respond({ method: 'PATCH', path: `${base}/ch-slack`, body: { data: { ...slack, is_enabled: false } } });

    await page.user.click((await card('Ops Slack')).getByRole('button', { name: 'Edit' }));
    const dialog = within(await screen.findByRole('dialog', { name: 'Edit Ops Slack' }));
    expect(dialog.getByLabelText('Webhook URL')).toHaveValue('');
    expect(dialog.getByText('Saved webhook on hooks.slack.com, ending in wxyz. Leave empty to keep it.')).toBeInTheDocument();
    expect(dialog.queryByRole('radio')).toBeNull();
    await page.user.click(dialog.getByRole('switch', { name: 'Send alerts' }));
    await page.user.click(dialog.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(page.server.requestsTo('PATCH', `${base}/ch-slack`)[0]?.body).toEqual({ is_enabled: false });
    });
  });

  it('sends a test and says it is queued', async () => {
    const page = openChannels();
    page.server.respond({ method: 'POST', path: `${base}/ch-mail/test`, status: 202, body: { data: { delivery_id: 'del-1' } } });

    await page.user.click((await card('On-call email')).getByRole('button', { name: 'Send test' }));

    expect(await (await card('On-call email')).findByText('Test alert queued. It arrives in a few seconds.')).toBeInTheDocument();
  });

  it('says when too many tests were sent', async () => {
    const page = openChannels();
    page.server.respond({ method: 'POST', path: `${base}/ch-mail/test`, status: 429, headers: { 'Retry-After': '42' }, body: { message: 'Too many.', code: 'test_limit_reached' } });

    await page.user.click((await card('On-call email')).getByRole('button', { name: 'Send test' }));

    expect(await (await card('On-call email')).findByText('Too many test alerts. Please wait a moment.')).toBeInTheDocument();
  });

  it('deletes a channel only after the confirmation', async () => {
    const page = openChannels();
    page.server.respond({ method: 'DELETE', path: `${base}/ch-slack`, status: 204 });

    await page.user.click((await card('Ops Slack')).getByRole('button', { name: 'Delete' }));
    expect(page.server.requestsTo('DELETE', `${base}/ch-slack`)).toEqual([]);
    await page.user.click(within(await screen.findByRole('alertdialog', { name: 'Delete Ops Slack?' })).getByRole('button', { name: 'Delete channel' }));

    await waitFor(() => {
      expect(page.server.requestsTo('DELETE', `${base}/ch-slack`)).toHaveLength(1);
    });
  });

  it('shows no write action to a member', async () => {
    openChannels(false);

    const mail = await card('On-call email');
    expect(mail.queryByRole('button')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Add channel' })).toBeNull();
  });
});
