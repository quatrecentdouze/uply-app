import type { Meta } from '@/shared/api/meta';
import type { AlertChannel, Incident, Me, Monitor, MonitorListResponse, Overview, PublicBar, PublicStatusPage } from '@/shared/api/resources';

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
    keyword: null,
    keyword_match: 'present',
    failure_threshold: 2,
    recovery_threshold: 2,
    request_header_names: [],
    has_request_body: false,
    is_paused: false,
    state: 'up',
    state_changed_at: '2026-10-29T08:00:00Z',
    last_checked_at: '2026-10-29T11:59:00Z',
    last_response_ms: 120,
    tls_expires_at: null,
    uptime_24h: 99.5,
    maintenance: null,
    flapping_since: null,
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
      reminder_intervals_minutes: [15, 30, 60, 120, 240, 720, 1440],
      limits: { max_reminders_per_incident: 24 },
    },
    api: { pagination: { default_per_page: 25, max_per_page: 100 }, rate_limit_per_minute: 120 },
    maintenance: {
      lengths: { name: { min: 1, max: 100 } },
      limits: { max_open_windows: 50, min_monitors: 1, max_monitors: 100 },
      max_duration_hours: 168,
      max_days_ahead: 90,
      start_grace_seconds: 60,
      states: ['upcoming', 'active', 'ended'],
    },
    monitoring: {
      intervals: { allowed_seconds: [30, 60, 120, 300, 600, 900, 1800, 3600], default_seconds: 60 },
      timeouts: { min_seconds: 1, max_seconds: 30, default_seconds: 10 },
      methods: ['get', 'head', 'post', 'put', 'patch'],
      body_methods: ['post', 'put', 'patch'],
      default_method: 'get',
      default_accepted_status_codes: ['200-299'],
      keyword_matches: ['present', 'absent'],
      default_keyword_match: 'present',
      thresholds: { min: 1, max: 10, default_failure: 2, default_recovery: 2 },
      tls: { expiry_alert_days: [14, 7, 1] },
      flapping: { changes: 5, window_minutes: 30, stable_minutes: 30 },
      refused_header_names: ['host', 'content-length', 'transfer-encoding', 'connection', 'keep-alive', 'upgrade', 'te', 'trailer', 'expect', 'proxy-authorization', 'proxy-connection'],
      windows: ['24h', '7d', '30d'],
      limits: { max_monitors_per_organization: 100, max_accepted_status_ranges: 20, max_request_headers: 20 },
      lengths: {
        monitor_name: { min: 1, max: 100 },
        url: { max: 2048 },
        keyword: { min: 1, max: 200 },
        request_header_name: { min: 1, max: 100 },
        request_header_value: { max: 1000 },
        request_body: { max: 10000 },
      },
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

export function alertChannelFixture(overrides: Partial<AlertChannel> = {}): AlertChannel {
  return {
    id: '01jbxchannel00000000000001',
    type: 'email',
    name: 'On-call email',
    is_enabled: true,
    disabled_reason: null,
    recipients: ['ops@example.test'],
    webhook_hint: null,
    reminder_interval_minutes: null,
    created_at: '2026-10-28T08:00:00Z',
    updated_at: '2026-10-28T08:00:00Z',
    ...overrides,
  };
}

const barDays = 90;

export function publicBarsFixture(uptime: (index: number) => number | null = () => 100): PublicBar[] {
  return Array.from({ length: barDays }, (_, index) => ({
    date: new Date(Date.UTC(2026, 7, 1 + index)).toISOString().slice(0, 10),
    uptime_percent: uptime(index),
    maintenance: false,
  }));
}

export function publicStatusPageFixture(overrides: Partial<PublicStatusPage> = {}): PublicStatusPage {
  return {
    title: 'Demo services',
    description: 'Live state of the demo services.',
    overall_state: 'partial_outage',
    monitors: [
      { id: '01jbxmonitor00000000000001', display_name: 'Website', state: 'up', uptime_90d: 99.95, bars: publicBarsFixture() },
      { id: '01jbxmonitor00000000000002', display_name: 'Public API', state: 'down', uptime_90d: 98.5, bars: publicBarsFixture((index) => (index === 89 ? 62.5 : index < 10 ? null : 100)) },
    ],
    incidents: [
      { display_name: 'Public API', started_at: '2026-10-29T08:15:00Z', resolved_at: null, duration_seconds: null },
      { display_name: 'Website', started_at: '2026-10-27T13:00:00Z', resolved_at: '2026-10-27T13:04:12Z', duration_seconds: 252 },
    ],
    ...overrides,
  };
}
