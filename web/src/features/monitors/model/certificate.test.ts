import { describe, expect, it } from 'vitest';
import { certificateLine } from '@/features/monitors/model/certificate';

const now = new Date('2026-10-29T12:00:00Z');
const alertDays = [14, 7, 1];

describe('certificateLine', () => {
  it('says no certificate yet before a check got one', () => {
    expect(certificateLine(null, now, alertDays)).toEqual({ key: 'monitors:detail.settings.certificate_none', expiresAt: null, daysLeft: null, tone: 'neutral' });
  });

  it('counts the days left of a certificate far from its end', () => {
    expect(certificateLine('2026-12-25T22:56:35Z', now, alertDays)).toEqual({
      key: 'monitors:detail.settings.certificate_days_left',
      expiresAt: '2026-12-25T22:56:35Z',
      daysLeft: 58,
      tone: 'positive',
    });
  });

  it('warns within the largest alert threshold of meta, its last second included', () => {
    expect(certificateLine('2026-11-12T12:00:00Z', now, alertDays)).toMatchObject({ daysLeft: 14, tone: 'warning' });
    expect(certificateLine('2026-11-12T12:00:01Z', now, alertDays)).toMatchObject({ daysLeft: 15, tone: 'positive' });
    expect(certificateLine('2026-10-29T13:00:00Z', now, alertDays)).toMatchObject({ daysLeft: 1, tone: 'warning' });
    expect(certificateLine('2026-11-12T12:00:00Z', now, [30])).toMatchObject({ tone: 'warning' });
  });

  it('turns to alert once the end is reached', () => {
    expect(certificateLine('2026-10-29T12:00:00Z', now, alertDays)).toEqual({
      key: 'monitors:detail.settings.certificate_expired',
      expiresAt: '2026-10-29T12:00:00Z',
      daysLeft: null,
      tone: 'alert',
    });
    expect(certificateLine('2026-10-01T00:00:00Z', now, alertDays)).toMatchObject({ tone: 'alert' });
  });

  it('never warns without thresholds', () => {
    expect(certificateLine('2026-10-30T12:00:00Z', now, [])).toMatchObject({ daysLeft: 1, tone: 'positive' });
  });
});
