import type { Incident, Me, Monitor, MonitorListResponse, Overview } from '@/shared/api/resources';

export const organizationId = '01jbxorganization0000000000';

export function monitorFixture(overrides: Partial<Monitor> = {}): Monitor {
  return {
    id: '01jbxmonitor00000000000001',
    name: 'Public API',
    url: 'https://api.example.test/health',
    method: 'get',
    interval_seconds: 60,
    timeout_seconds: 10,
    accepted_status_codes: ['200-299'],
    follow_redirects: true,
    is_paused: false,
    state: 'up',
    state_changed_at: '2026-10-29T08:00:00Z',
    last_checked_at: '2026-10-29T11:59:00Z',
    last_response_ms: 120,
    uptime_24h: 99.5,
    created_at: '2026-10-28T08:00:00Z',
    updated_at: '2026-10-28T08:00:00Z',
    ...overrides,
  };
}

export function listOf<T>(items: T[]) {
  return {
    data: items,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: items.length === 0 ? null : 1, last_page: 1, links: [], path: null, per_page: 25, to: items.length === 0 ? null : items.length, total: items.length },
  };
}

export function monitorListFixture(monitors: Monitor[]): MonitorListResponse {
  return listOf(monitors);
}

export function incidentFixture(overrides: Partial<Incident> = {}): Incident {
  return {
    id: '01jbxincident0000000000001',
    monitor: { id: '01jbxmonitor00000000000001', name: 'Public API' },
    started_at: '2026-10-29T10:00:00Z',
    resolved_at: null,
    duration_seconds: null,
    cause: { failure_kind: 'http_status', http_status: 503, message: null },
    ...overrides,
  };
}

export function overviewFixture(overrides: Partial<Overview> = {}): Overview {
  return {
    window: '24h',
    counts: { up: 3, down: 1, pending: 0, paused: 1 },
    uptime_percent: 98.75,
    series: [
      { start: '2026-10-29T09:00:00Z', uptime_percent: 100, avg_response_ms: 110 },
      { start: '2026-10-29T10:00:00Z', uptime_percent: 95, avg_response_ms: 180 },
      { start: '2026-10-29T11:00:00Z', uptime_percent: null, avg_response_ms: null },
    ],
    ...overrides,
  };
}

export function meFixture(overrides: Partial<Me> = {}): Me {
  return {
    id: '01jbxuser00000000000000001',
    name: 'Demo User',
    email: 'demo@uply.test',
    email_verified_at: '2026-10-28T08:00:00Z',
    two_factor_enabled: false,
    created_at: '2026-10-28T08:00:00Z',
    memberships: [
      {
        id: '01jbxmembership00000000001',
        role: 'owner',
        organization: { id: organizationId, name: 'Demo', slug: 'demo' },
        joined_at: '2026-10-28T08:00:00Z',
      },
    ],
    ...overrides,
  };
}
