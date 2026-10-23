import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CheckSettingsCard } from '@/features/monitors/components/CheckSettingsCard';
import type { Monitor } from '@/shared/api/resources';
import { monitorFixture } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

const now = new Date('2026-10-29T12:00:00Z');

function renderCard(monitor: Monitor) {
  renderPage(() => <CheckSettingsCard monitor={monitor} now={now} alertDays={[14, 7, 1]} />);
}

async function settings(): Promise<Record<string, string>> {
  const card = await screen.findByRole('region', { name: 'Check settings' });
  return Object.fromEntries(
    within(card)
      .getAllByRole('term')
      .map((term) => [term.textContent, term.nextElementSibling?.textContent ?? '']),
  );
}

describe('CheckSettingsCard', () => {
  it('shows None for a plain monitor without certificate', async () => {
    renderCard(monitorFixture());

    expect(await settings()).toEqual({
      Method: 'GET',
      Keyword: 'None',
      'Request headers': 'None',
      'Request body': 'None',
      'Failures before down': '2 checks in a row',
      'Successes before up': '2 checks in a row',
      'TLS certificate': 'No certificate yet',
    });
  });

  it('shows the keyword with its rule, the header names, the body and the certificate', async () => {
    renderCard(
      monitorFixture({
        method: 'post',
        keyword: 'Welcome',
        keyword_match: 'absent',
        request_header_names: ['Authorization', 'X-Trace'],
        has_request_body: true,
        failure_threshold: 1,
        recovery_threshold: 3,
        tls_expires_at: '2026-12-25T22:56:35Z',
      }),
    );

    const values = await settings();
    expect(values).toMatchObject({
      Method: 'POST',
      Keyword: 'WelcomeMust not contain',
      'Request headers': 'AuthorizationX-Trace',
      'Request body': 'Sent',
      'Failures before down': '1 check in a row',
      'Successes before up': '3 checks in a row',
    });
    expect(within(screen.getByRole('list', { name: 'Request header names' })).getAllByRole('listitem')).toHaveLength(2);
    expect(values['TLS certificate']).toMatch(/2026/);
    expect(values['TLS certificate']).toContain('58 days left');
  });

  it('warns about a certificate close to its end and alerts past it', async () => {
    renderCard(monitorFixture({ tls_expires_at: '2026-11-03T12:00:00Z' }));
    expect((await settings())['TLS certificate']).toContain('5 days left');
    expect(screen.getByText('5 days left')).toHaveClass('text-warning');
  });

  it('says a past certificate expired', async () => {
    renderCard(monitorFixture({ tls_expires_at: '2026-10-28T12:00:00Z' }));
    expect((await settings())['TLS certificate']).toContain('Expired');
    expect(screen.getByText('Expired')).toHaveClass('text-alert');
  });
});
