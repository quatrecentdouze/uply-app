import type { Meta } from '@/shared/api/meta';
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

const viewAbilities = ['monitors.view', 'incidents.view', 'status-pages.view', 'alert-channels.view'];
const memberAbilities = [...viewAbilities, 'monitors.manage', 'status-pages.manage'];
const adminAbilities = [...memberAbilities, 'alert-channels.manage', 'members.manage', 'organization.manage'];

export function metaFixture(overrides: Partial<Meta> = {}): Meta {
  return {
    alerting: {
      channel_types: ['email', 'discord', 'slack'],
      email_recipients: { min: 1, max: 10 },
      test_sends_per_minute: 5,
      max_channels_per_monitor: 50,
      lengths: { channel_name: { min: 1, max: 80 }, webhook_url: { max: 2048 } },
    },
    api: { pagination: { default_per_page: 25, max_per_page: 100 }, rate_limit_per_minute: 120 },
    monitoring: {
      intervals: { allowed_seconds: [30, 60, 120, 300, 600, 900, 1800, 3600], default_seconds: 60 },
      timeouts: { min_seconds: 1, max_seconds: 30, default_seconds: 10 },
      methods: ['get', 'head'],
      default_method: 'get',
      default_accepted_status_codes: ['200-299'],
      windows: ['24h', '7d', '30d'],
      limits: { max_monitors_per_organization: 100, max_accepted_status_ranges: 20 },
      lengths: { monitor_name: { min: 1, max: 100 }, url: { max: 2048 } },
    },
    organizations: {
      roles: [
        { id: 'owner', abilities: [...adminAbilities, 'organization.delete'] },
        { id: 'admin', abilities: adminAbilities },
        { id: 'member', abilities: memberAbilities },
        { id: 'viewer', abilities: viewAbilities },
      ],
      abilities: [...adminAbilities, 'organization.delete'],
      limits: { max_owned_organizations: 10, max_pending_invitations: 50, invitation_valid_days: 7 },
      lengths: {
        organization_name: { min: 2, max: 80 },
        slug: { min: 3, max: 48 },
        user_name: { min: 1, max: 80 },
        password: { min: 12, max: 255 },
      },
    },
    realtime: { key: null, host: 'localhost', port: 6001, scheme: 'http' },
    status_pages: {
      limits: { max_pages_per_organization: 10, max_monitors_per_page: 50 },
      lengths: { title: { min: 1, max: 80 }, description: { max: 500 }, display_name: { max: 80 }, slug: { min: 3, max: 48, pattern: '^[a-z0-9-]+$' } },
      public: { bar_days: 90, incident_days: 14, cache_seconds: 30 },
      overall_states: ['operational', 'partial_outage', 'major_outage'],
    },
    ...overrides,
  };
}
