import { cn } from 'cn';
import { Info } from 'lucide-react';
import { type ComponentProps, type ReactNode, useId } from 'react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/shared/ui/tooltip';

export function SurfaceCard({ className, ...props }: ComponentProps<'section'>) {
  return <section className={cn('relative min-w-0 rounded-card border-2 border-line bg-surface', className)} {...props} />;
}

interface CardHeadingProps {
  id?: string;
  title: string;
  info?: string;
  infoLabel?: string;
  action?: ReactNode;
}

export function CardHeading({ id, title, info, infoLabel, action }: CardHeadingProps) {
  const fallbackId = useId();
  return (
    <div className="flex min-h-10 items-center justify-between gap-3">
      <h2 id={id ?? fallbackId} className="flex items-center gap-[9px] text-[17px] leading-[23px] font-light whitespace-nowrap 2xl:text-lg">
        {title}
        {info === undefined ? null : (
          <Tooltip>
            <TooltipTrigger aria-label={infoLabel ?? info} className="-m-2.5 grid size-10 place-items-center rounded-full text-faint">
              <Info className="size-[19px]" strokeWidth={1.4} aria-hidden="true" />
            </TooltipTrigger>
            <TooltipContent className="max-w-64">{info}</TooltipContent>
          </Tooltip>
        )}
      </h2>
      {action}
    </div>
  );
}

const pillTones = {
  positive: 'bg-positive-soft text-positive',
  alert: 'bg-alert-soft text-alert',
  warning: 'bg-warning-soft text-warning',
  pending: 'bg-pending-soft text-pending',
  paused: 'bg-paused-soft text-paused',
  neutral: 'bg-chip text-muted-foreground',
} as const;

export type PillTone = keyof typeof pillTones;

export function Pill({ tone, children, className }: { tone: PillTone; children: ReactNode; className?: string }) {
  return (
    <span className={cn('inline-flex items-center gap-[5px] rounded-full px-[9px] py-[5px] text-sm leading-[17px] whitespace-nowrap', pillTones[tone], className)}>
      <span aria-hidden="true" className="size-[7px] rounded-full bg-current" />
      {children}
    </span>
  );
}
