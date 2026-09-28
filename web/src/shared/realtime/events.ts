import { z } from 'zod';

const monitorState = z.enum(['pending', 'up', 'down']);

export const realtimeEventSchemas = {
  'monitor.checked': z.object({
    monitor_id: z.string(),
    checked_at: z.string(),
    outcome: z.enum(['up', 'down']),
    response_ms: z.number().int().nullable(),
    state: monitorState,
  }),
  'monitor.state-changed': z.object({
    monitor_id: z.string(),
    state: monitorState,
    changed_at: z.string(),
  }),
  'monitor.changed': z.object({
    monitor_id: z.string(),
    change: z.enum(['created', 'updated', 'deleted']),
  }),
  'incident.opened': z.object({
    incident_id: z.string(),
    monitor_id: z.string(),
    started_at: z.string(),
  }),
  'incident.resolved': z.object({
    incident_id: z.string(),
    monitor_id: z.string(),
    resolved_at: z.string(),
    duration_seconds: z.number().int(),
  }),
} as const;

type Schemas = typeof realtimeEventSchemas;

export type RealtimeEventName = keyof Schemas;

export type RealtimeEvent = {
  [Name in RealtimeEventName]: { name: Name; payload: z.infer<Schemas[Name]> };
}[RealtimeEventName];

export const realtimeEventNames = [
  'monitor.checked',
  'monitor.state-changed',
  'monitor.changed',
  'incident.opened',
  'incident.resolved',
] as const satisfies readonly RealtimeEventName[];

export function parseRealtimeEvent(name: RealtimeEventName, payload: unknown): RealtimeEvent | null {
  switch (name) {
    case 'monitor.checked': {
      const parsed = realtimeEventSchemas[name].safeParse(payload);
      return parsed.success ? { name, payload: parsed.data } : null;
    }
    case 'monitor.state-changed': {
      const parsed = realtimeEventSchemas[name].safeParse(payload);
      return parsed.success ? { name, payload: parsed.data } : null;
    }
    case 'monitor.changed': {
      const parsed = realtimeEventSchemas[name].safeParse(payload);
      return parsed.success ? { name, payload: parsed.data } : null;
    }
    case 'incident.opened': {
      const parsed = realtimeEventSchemas[name].safeParse(payload);
      return parsed.success ? { name, payload: parsed.data } : null;
    }
    case 'incident.resolved': {
      const parsed = realtimeEventSchemas[name].safeParse(payload);
      return parsed.success ? { name, payload: parsed.data } : null;
    }
  }
}

export function organizationChannel(organizationId: string): string {
  return `organization.${organizationId}`;
}
