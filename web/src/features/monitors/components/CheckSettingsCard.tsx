import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { keywordMatchTextKeys } from '@/features/monitors/model/advancedForm';
import { certificateLine } from '@/features/monitors/model/certificate';
import { methodTextKeys } from '@/features/monitors/model/monitorForm';
import type { Monitor } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';

function SettingRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="grid gap-1 border-b border-divider py-3 last:border-b-0 sm:grid-cols-[minmax(0,11rem)_minmax(0,1fr)] sm:items-baseline sm:gap-4">
      <dt className="text-sm text-muted-foreground">{label}</dt>
      <dd className="flex min-w-0 flex-wrap items-center gap-2">{children}</dd>
    </div>
  );
}

function CertificateValue({ monitor, now, alertDays }: { monitor: Monitor; now: Date; alertDays: readonly number[] }) {
  const { t, i18n } = useTranslation(['monitors']);
  const line = certificateLine(monitor.tls_expires_at, now, alertDays);
  if (line.expiresAt === null) {
    return <span className="text-muted-foreground">{t(line.key)}</span>;
  }
  return (
    <>
      <time dateTime={line.expiresAt}>{formatDate(line.expiresAt, { locale: i18n.language }, { dateStyle: 'medium' })}</time>
      <Pill tone={line.tone}>{line.daysLeft === null ? t(line.key) : t(line.key, { count: line.daysLeft })}</Pill>
    </>
  );
}

interface CheckSettingsCardProps {
  monitor: Monitor;
  now: Date;
  alertDays: readonly number[];
}

export function CheckSettingsCard({ monitor, now, alertDays }: CheckSettingsCardProps) {
  const { t } = useTranslation(['monitors']);
  const none = <span className="text-muted-foreground">{t('monitors:detail.settings.none')}</span>;
  return (
    <SurfaceCard aria-labelledby="check-settings-title" className="p-4 sm:p-5">
      <CardHeading id="check-settings-title" title={t('monitors:detail.settings.title')} info={t('monitors:detail.settings.info')} />
      <dl className="mt-2">
        <SettingRow label={t('monitors:form.method')}>{t(methodTextKeys[monitor.method])}</SettingRow>
        <SettingRow label={t('monitors:form.advanced.keyword')}>
          {monitor.keyword === null ? (
            none
          ) : (
            <>
              <span className="min-w-0 rounded-[9px] bg-chip px-2.5 py-1 font-mono text-sm break-all">{monitor.keyword}</span>
              <span className="text-sm text-muted-foreground">{t(keywordMatchTextKeys[monitor.keyword_match])}</span>
            </>
          )}
        </SettingRow>
        <SettingRow label={t('monitors:form.advanced.headers')}>
          {monitor.request_header_names.length === 0 ? (
            none
          ) : (
            <ul aria-label={t('monitors:detail.settings.header_names')} className="flex min-w-0 flex-wrap gap-2">
              {monitor.request_header_names.map((name) => (
                <li key={name} className="rounded-[9px] bg-chip px-2.5 py-1 font-mono text-sm break-all">
                  {name}
                </li>
              ))}
            </ul>
          )}
        </SettingRow>
        <SettingRow label={t('monitors:form.advanced.body')}>{monitor.has_request_body ? t('monitors:detail.settings.body_sent') : none}</SettingRow>
        <SettingRow label={t('monitors:form.advanced.failure_threshold')}>{t('monitors:detail.settings.in_a_row', { count: monitor.failure_threshold })}</SettingRow>
        <SettingRow label={t('monitors:form.advanced.recovery_threshold')}>{t('monitors:detail.settings.in_a_row', { count: monitor.recovery_threshold })}</SettingRow>
        <SettingRow label={t('monitors:detail.settings.certificate')}>
          <CertificateValue monitor={monitor} now={now} alertDays={alertDays} />
        </SettingRow>
      </dl>
    </SurfaceCard>
  );
}
