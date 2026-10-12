import { z } from 'zod';
import shipped from '@/features/status-pages/model/publicPageSettings.json';

const settingsSchema = z.object({
  compact_bars: z.number().int().min(1),
});

export type PublicPageSettings = z.infer<typeof settingsSchema>;

export const publicPageSettingsSource = 'src/features/status-pages/model/publicPageSettings.json';

export function parsePublicPageSettings(values: unknown): PublicPageSettings {
  const parsed = settingsSchema.safeParse(values);
  if (!parsed.success) {
    const issue = parsed.error.issues[0];
    throw new Error(`${publicPageSettingsSource}: ${issue?.path.join('.') ?? ''} ${issue?.message ?? ''}`);
  }
  return parsed.data;
}

export const publicPageSettings: PublicPageSettings = parsePublicPageSettings(shipped);
