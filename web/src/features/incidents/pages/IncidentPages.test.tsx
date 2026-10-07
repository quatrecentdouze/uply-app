import { screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { IncidentDetailPage } from '@/features/incidents/pages/IncidentDetailPage';
import { IncidentsPage } from '@/features/incidents/pages/IncidentsPage';
import type { TimelineEvent } from '@/shared/api/resources';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { incidentFixture, listOf, organizationId } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

const base = `/api/v1/organizations/${organizationId}/incidents`;
const now = new Date('2026-10-29T12:00:00Z');

function timelineEvent(overrides: Partial<TimelineEvent>): TimelineEvent {
  return {
    kind: 'opened',
    occurred_at: '2026-10-29T10:00:00Z',
    failure_kind: null,
    http_status: null,
    message: null,
    duration_seconds: null,
    channel_id: null,
    channel_type: null,
    alert_kind: null,
    attempts: null,
    ...overrides,
  };
}

describe('IncidentsPage', () => {
  it('lists the incidents and filters the ongoing ones', async () => {
    const server = fakeFetch([{ method: 'GET', path: base, body: listOf([incidentFixture({ id: 'inc-1' }), incidentFixture({ id: 'inc-2', resolved_at: '2026-10-29T10:04:12Z', duration_seconds: 252 })]) }]);
    const page = renderPage(() => <IncidentsPage organizationId={organizationId} now={now} />, { server });

    const table = within(await screen.findByRole('table', { name: 'Incidents of the organization' }));
    expect(table.getByText('4 min 12 s')).toBeInTheDocument();
    expect(table.getAllByRole('link', { name: 'Open the incident of Public API' })[0]).toHaveAttribute('href', '/incidents/inc-1');

    await page.user.click(screen.getByRole('button', { name: 'Ongoing' }));

    await waitFor(() => {
      expect(server.requestsTo('GET', base).map((request) => request.search)).toContain('?state=open&page=1');
    });
  });

  it('moves between the pages of the list', async () => {
    const list = listOf([incidentFixture()]);
    const server = fakeFetch([{ method: 'GET', path: base, body: { ...list, meta: { ...list.meta, current_page: 1, last_page: 2, total: 30 } } }]);
    const page = renderPage(() => <IncidentsPage organizationId={organizationId} now={now} />, { server });

    expect(await screen.findByText('Page 1 of 2')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Previous' })).toBeDisabled();
    await page.user.click(screen.getByRole('button', { name: 'Next' }));

    await waitFor(() => {
      expect(server.requestsTo('GET', base).map((request) => request.search)).toContain('?page=2');
    });
  });

  it('says when no incident happened yet', async () => {
    renderPage(() => <IncidentsPage organizationId={organizationId} now={now} />, { server: fakeFetch([{ method: 'GET', path: base, body: listOf([]) }]) });

    expect(await screen.findByText('No incident so far. Everything is running.')).toBeInTheDocument();
  });
});

describe('IncidentDetailPage', () => {
  it('shows the summary and the timeline oldest first', async () => {
    const incident = incidentFixture({
      id: 'inc-1',
      resolved_at: '2026-10-29T10:04:12Z',
      duration_seconds: 252,
      timeline: [
        timelineEvent({ kind: 'resolved', occurred_at: '2026-10-29T10:04:12Z', duration_seconds: 252 }),
        timelineEvent({ kind: 'alert_failed', occurred_at: '2026-10-29T10:01:00Z', channel_type: 'slack', alert_kind: 'down', attempts: 5 }),
        timelineEvent({ kind: 'opened', occurred_at: '2026-10-29T10:00:00Z', failure_kind: 'http_status', http_status: 503 }),
      ],
    });
    renderPage(() => <IncidentDetailPage organizationId={organizationId} incidentId="inc-1" now={now} notFound={null} />, {
      server: fakeFetch([{ method: 'GET', path: `${base}/inc-1`, body: { data: incident } }]),
    });

    expect(await screen.findByRole('link', { name: 'Public API' })).toHaveAttribute('href', `/monitors/${incident.monitor.id}`);
    const items = within(screen.getByRole('list')).getAllByRole('listitem');
    expect(items.map((item) => item.querySelector('span:nth-child(2)')?.textContent)).toEqual(['The monitor went down', 'Down alert failed on Slack', 'The monitor came back up']);
    expect(screen.getByText('5 tries')).toBeInTheDocument();
    expect(screen.getByText('Back up after 4 min 12 s')).toBeInTheDocument();
  });

  it('shows the not found page for an unknown incident', async () => {
    renderPage(() => <IncidentDetailPage organizationId={organizationId} incidentId="nope" now={now} notFound={<p>Gone</p>} />, {
      server: fakeFetch([{ method: 'GET', path: `${base}/nope`, status: 404, body: { message: 'Not found.' } }]),
    });

    expect(await screen.findByText('Gone')).toBeInTheDocument();
  });
});
