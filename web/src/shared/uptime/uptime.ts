import { z } from 'zod';
import shipped from '@/shared/uptime/uptimeSettings.json';

export type Tone = 'positive' | 'warning' | 'alert' | 'neutral';

const settingsSchema = z
  .object({
    thresholds: z.object({
      healthy: z.number().min(0).max(100),
      degraded: z.number().min(0).max(100),
    }),
  })
  .refine((settings) => settings.thresholds.degraded <= settings.thresholds.healthy, {
    path: ['thresholds', 'degraded'],
    message: 'must not exceed thresholds.healthy',
  });

export type UptimeSettings = z.infer<typeof settingsSchema>;

export type UptimeThresholds = UptimeSettings['thresholds'];

export const uptimeSettingsSource = 'src/shared/uptime/uptimeSettings.json';

export function parseUptimeSettings(values: unknown): UptimeSettings {
  const parsed = settingsSchema.safeParse(values);
  if (!parsed.success) {
    const issue = parsed.error.issues[0];
    throw new Error(`${uptimeSettingsSource}: ${issue?.path.join('.') ?? ''} ${issue?.message ?? ''}`);
  }
  return parsed.data;
}

export const uptimeSettings: UptimeSettings = parseUptimeSettings(shipped);

export function uptimeTone(percent: number | null, thresholds: UptimeThresholds): Tone {
  if (percent === null) {
    return 'neutral';
  }
  if (percent >= thresholds.healthy) {
    return 'positive';
  }
  return percent >= thresholds.degraded ? 'warning' : 'alert';
}
