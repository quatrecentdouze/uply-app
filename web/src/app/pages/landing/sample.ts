import { z } from 'zod';
import shipped from '@/app/pages/landing/sample.json';
import type { CheckResult, Incident, Monitor, Overview, PublicStatusPage } from '@/shared/api/resources';

const percent = z.number().min(0).max(100);
const instant = z.iso.datetime();

const sampleSchema = z.object({
  now: instant,
  monitors: z
    .array(
      z.object({
        id: z.string(),
        name: z.string(),
        url: z.url({ protocol: /^https$/, hostname: /(^|\.)example\.com$/ }),
        state: z.enum(['up', 'down', 'pending']),
        is_paused: z.boolean(),
        last_response_ms: z.number().int().min(0).nullable(),
        uptime_24h: percent.nullable(),
      }),
    )
    .min(1),
  incidents: z.array(
    z.object({
      id: z.string(),
      monitor_id: z.string(),
      started_at: instant,
      resolved_at: instant.nullable(),
      failure_kind: z.string().nullable(),
      http_status: z.number().int().nullable(),
    }),
  ),
  checks: z.array(
    z.object({
      checked_at: instant,
      outcome: z.enum(['up', 'down']),
      failure_kind: z.enum(['timeout', 'dns', 'connect', 'tls', 'http_status', 'too_many_redirects', 'blocked_target', 'body_too_large', 'unknown']).nullable(),
      http_status: z.number().int().nullable(),
      response_ms: z.number().int().min(0).nullable(),
    }),
  ),
  status_page: z.object({
    title: z.string(),
    overall_state: z.enum(['operational', 'partial_outage', 'major_outage']),
    monitors: z.array(
      z.object({
        id: z.string(),
        display_name: z.string(),
        state: z.enum(['up', 'down', 'pending', 'paused']),
        uptime_90d: percent.nullable(),
        bars: z.array(percent.nullable()).min(1),
      }),
    ),
  }),
});

type SampleData = z.infer<typeof sampleSchema>;

export interface LandingSample {
  now: Date;
  monitors: Monitor[];
  overview: Overview;
  incidents: Incident[];
  checks: CheckResult[];
  statusPage: PublicStatusPage;
}

export const sampleSource = 'src/app/pages/landing/sample.json';

const millisecondsPerSecond = 1000;
const millisecondsPerDay = 86_400_000;

function secondsBetween(start: string, end: string): number {
  return Math.round((new Date(end).getTime() - new Date(start).getTime()) / millisecondsPerSecond);
}

function dayBefore(now: string, days: number): string {
  return new Date(new Date(now).getTime() - days * millisecondsPerDay).toISOString().slice(0, 10);
}

function monitorsOf(data: SampleData): Monitor[] {
  return data.monitors.map((monitor) => ({
    ...monitor,
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
    state_changed_at: null,
    last_checked_at: data.now,
    created_at: null,
    updated_at: null,
  }));
}

function overviewOf(monitors: readonly Monitor[]): Overview {
  const active = monitors.filter((monitor) => !monitor.is_paused);
  return {
    window: '24h',
    counts: {
      up: active.filter((monitor) => monitor.state === 'up').length,
      down: active.filter((monitor) => monitor.state === 'down').length,
      pending: active.filter((monitor) => monitor.state === 'pending').length,
      paused: monitors.length - active.length,
    },
    uptime_percent: null,
    series: [],
  };
}

function incidentsOf(data: SampleData): Incident[] {
  return data.incidents.map((incident) => ({
    id: incident.id,
    monitor: { id: incident.monitor_id, name: data.monitors.find((monitor) => monitor.id === incident.monitor_id)?.name ?? null },
    started_at: incident.started_at,
    resolved_at: incident.resolved_at,
    duration_seconds: incident.resolved_at === null ? null : secondsBetween(incident.started_at, incident.resolved_at),
    cause: { failure_kind: incident.failure_kind, http_status: incident.http_status, message: null },
  }));
}

function checksOf(data: SampleData): CheckResult[] {
  return data.checks.map((check) => ({ ...check, dns_ms: null, connect_ms: null, tls_ms: null, ttfb_ms: null, error_message: null }));
}

function statusPageOf(data: SampleData): PublicStatusPage {
  return {
    title: data.status_page.title,
    description: null,
    overall_state: data.status_page.overall_state,
    monitors: data.status_page.monitors.map((monitor) => ({
      ...monitor,
      bars: monitor.bars.map((uptime_percent, index) => ({ date: dayBefore(data.now, monitor.bars.length - 1 - index), uptime_percent })),
    })),
    incidents: [],
  };
}

export function parseLandingSample(values: unknown): LandingSample {
  const parsed = sampleSchema.safeParse(values);
  if (!parsed.success) {
    const issue = parsed.error.issues[0];
    throw new Error(`${sampleSource}: ${issue?.path.join('.') ?? ''} ${issue?.message ?? ''}`);
  }
  const monitors = monitorsOf(parsed.data);
  return {
    now: new Date(parsed.data.now),
    monitors,
    overview: overviewOf(monitors),
    incidents: incidentsOf(parsed.data),
    checks: checksOf(parsed.data),
    statusPage: statusPageOf(parsed.data),
  };
}

export const landingSample: LandingSample = parseLandingSample(shipped);
