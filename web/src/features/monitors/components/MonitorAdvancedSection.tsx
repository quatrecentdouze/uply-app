import { cn } from 'cn';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { ThresholdFields } from '@/features/monitors/components/ThresholdFields';
import type { MonitoringMeta } from '@/features/monitors/model/monitorForm';
import { ChevronDown } from '@/shared/ui/icons';

interface MonitorAdvancedSectionProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  monitoring: MonitoringMeta;
}

export function MonitorAdvancedSection({ open, onOpenChange, monitoring }: MonitorAdvancedSectionProps) {
  const { t } = useTranslation(['monitors']);
  const id = useId();
  return (
    <div className="grid gap-5">
      <button
        type="button"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => {
          onOpenChange(!open);
        }}
        className="flex min-h-10 items-center justify-between gap-4 rounded-[13px] border-2 border-divider bg-surface-raised px-4 py-3 text-left hover:bg-chip"
      >
        <span className="grid gap-0.5">
          <span className="text-base">{t('monitors:form.advanced.title')}</span>
          <span className="text-sm text-muted-foreground">{t('monitors:form.advanced.summary')}</span>
        </span>
        <ChevronDown className={cn('size-5 shrink-0 text-muted-foreground motion-safe:transition-transform', open && 'rotate-180')} aria-hidden="true" />
      </button>
      <div id={id} hidden={!open} className="grid gap-5">
        <ThresholdFields thresholds={monitoring.thresholds} />
      </div>
    </div>
  );
}
