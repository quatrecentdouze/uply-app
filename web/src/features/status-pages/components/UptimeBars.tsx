import { cn } from 'cn';
import { type PointerEvent, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type BarView, barDateIso, barViews } from '@/features/status-pages/model/publicPage';
import type { PublicBar } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { type Tone, uptimeSettings } from '@/shared/uptime/uptime';

interface UptimeBarsProps {
  bars: readonly PublicBar[];
  label: string;
  compactCount: number;
}

const barTones: Record<Tone, string> = {
  positive: 'bg-positive',
  warning: 'bg-warning',
  alert: 'bg-alert',
  neutral: 'bg-line-strong',
};

interface ActiveBar {
  view: BarView;
  left: number;
}

const tooltipMargin = 64;

function barElementAt(event: PointerEvent<HTMLDivElement>): HTMLElement | null {
  const found = typeof document.elementFromPoint === 'function' ? document.elementFromPoint(event.clientX, event.clientY) : null;
  const target = found ?? event.target;
  return target instanceof HTMLElement && target.dataset.index !== undefined ? target : null;
}

export function UptimeBars({ bars, label, compactCount }: UptimeBarsProps) {
  const { t, i18n } = useTranslation(['status-pages']);
  const strip = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState<ActiveBar | null>(null);
  const views = barViews(bars, uptimeSettings.thresholds, compactCount);

  const point = (event: PointerEvent<HTMLDivElement>) => {
    const element = barElementAt(event);
    const view = element === null ? undefined : views[Number(element.dataset.index)];
    if (element === null || view === undefined || strip.current === null) {
      setActive(null);
      return;
    }
    const box = strip.current.getBoundingClientRect();
    const bar = element.getBoundingClientRect();
    const center = bar.left + bar.width / 2 - box.left;
    setActive({ view, left: Math.min(Math.max(center, tooltipMargin), Math.max(box.width - tooltipMargin, tooltipMargin)) });
  };

  const date = active === null ? '' : formatDate(barDateIso(active.view.date), { locale: i18n.language, timeZone: 'UTC' }, { month: 'short', day: 'numeric' });

  return (
    <div className="relative">
      <div
        ref={strip}
        role="img"
        aria-label={label}
        className="flex h-9 touch-pan-y gap-[2px] sm:gap-[3px]"
        onPointerMove={point}
        onPointerDown={point}
        onPointerLeave={() => {
          setActive(null);
        }}
      >
        {views.map((view) => (
          <span
            key={view.date}
            aria-hidden="true"
            data-index={view.index}
            data-tone={view.tone}
            className={cn('min-w-0 flex-1 rounded-[3px]', barTones[view.tone], view.compactHidden && 'max-sm:hidden', active?.view.index === view.index && 'opacity-60')}
          />
        ))}
      </div>
      {active === null ? null : (
        <p
          aria-hidden="true"
          data-testid="bar-tooltip"
          className="pointer-events-none absolute -top-10 z-10 -translate-x-1/2 rounded-[9px] border border-line-strong bg-popover px-2.5 py-1 text-sm whitespace-nowrap text-popover-foreground shadow-lg"
          style={{ left: `${String(active.left)}px` }}
        >
          {active.view.percent === null ? t('status-pages:public.bar_no_data', { date }) : t('status-pages:public.bar', { date, value: active.view.percent })}
        </p>
      )}
    </div>
  );
}
