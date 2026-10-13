import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { type OverallState, overallTextKeys, overallTones } from '@/features/status-pages/model/publicPage';
import { CircleAlert, CircleCheck, CircleX, type LucideIcon } from '@/shared/ui/icons';
import type { Tone } from '@/shared/uptime/uptime';

const toneClasses: Record<Tone, string> = {
  positive: 'border-positive/40 bg-positive-soft text-positive',
  warning: 'border-warning/40 bg-warning-soft text-warning',
  alert: 'border-alert/40 bg-alert-soft text-alert',
  neutral: 'border-line-strong bg-chip text-muted-foreground',
};

const toneIcons: Record<Tone, LucideIcon> = {
  positive: CircleCheck,
  warning: CircleAlert,
  alert: CircleX,
  neutral: CircleAlert,
};

export function StatusBanner({ state }: { state: OverallState | null }) {
  const { t } = useTranslation(['status-pages']);
  const tone = state === null ? 'neutral' : overallTones[state];
  const Icon = toneIcons[tone];
  return (
    <div role="status" className={cn('flex items-center gap-3.5 rounded-card border-2 px-4 py-4 sm:px-6 sm:py-5', toneClasses[tone])}>
      <Icon className="size-7 shrink-0" strokeWidth={1.6} aria-hidden="true" />
      <p className="text-lg leading-6 font-normal sm:text-xl">{state === null ? t('status-pages:public.overall.unknown') : t(overallTextKeys[state])}</p>
    </div>
  );
}
