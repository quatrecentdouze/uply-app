import { z } from 'zod';
import shipped from '@/features/monitors/model/dashboardSettings.json';

const settingsSchema = z
  .object({
    uptime_thresholds: z.object({
      healthy: z.number().min(0).max(100),
      degraded: z.number().min(0).max(100),
    }),
    slow_response_ms: z.number().int().min(1),
    panel_monitors: z.number().int().min(1),
    recent_incidents: z.number().int().min(1),
    list_size: z.number().int().min(1).max(100),
    default_window: z.enum(['24h', '7d', '30d']),
    clock_tick_seconds: z.number().int().min(1),
  })
  .refine((settings) => settings.uptime_thresholds.degraded <= settings.uptime_thresholds.healthy, {
    path: ['uptime_thresholds', 'degraded'],
    message: 'must not exceed uptime_thresholds.healthy',
  });

export type DashboardSettings = z.infer<typeof settingsSchema>;

export const settingsSource = 'src/features/monitors/model/dashboardSettings.json';

export function parseDashboardSettings(values: unknown): DashboardSettings {
  const parsed = settingsSchema.safeParse(values);
  if (!parsed.success) {
    const issue = parsed.error.issues[0];
    throw new Error(`${settingsSource}: ${issue?.path.join('.') ?? ''} ${issue?.message ?? ''}`);
  }
  return parsed.data;
}

export const dashboardSettings: DashboardSettings = parseDashboardSettings(shipped);
