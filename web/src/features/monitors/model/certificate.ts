import type { TextKey } from '@/shared/i18n/textKey';

export type CertificateTone = 'neutral' | 'positive' | 'warning' | 'alert';

export interface CertificateLine {
  key: TextKey<'monitors'>;
  expiresAt: string | null;
  daysLeft: number | null;
  tone: CertificateTone;
}

const millisecondsPerDay = 86_400_000;

export function certificateLine(expiresAt: string | null, now: Date, alertDays: readonly number[]): CertificateLine {
  if (expiresAt === null) {
    return { key: 'monitors:detail.settings.certificate_none', expiresAt: null, daysLeft: null, tone: 'neutral' };
  }
  const remaining = new Date(expiresAt).getTime() - now.getTime();
  if (remaining <= 0) {
    return { key: 'monitors:detail.settings.certificate_expired', expiresAt, daysLeft: null, tone: 'alert' };
  }
  const warningDays = Math.max(0, ...alertDays);
  return {
    key: 'monitors:detail.settings.certificate_days_left',
    expiresAt,
    daysLeft: Math.ceil(remaining / millisecondsPerDay),
    tone: remaining <= warningDays * millisecondsPerDay ? 'warning' : 'positive',
  };
}
