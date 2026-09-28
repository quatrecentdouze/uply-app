import { cn } from 'cn';

interface BrandProps {
  name: string;
  compact?: boolean;
  className?: string;
}

export function BrandMark({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'grid size-[50px] shrink-0 place-items-center rounded-[14px] bg-linear-150 from-kbd to-surface shadow-[inset_0_2px_2px] shadow-bright/15',
        className,
      )}
    >
      <svg viewBox="0 0 36 36" className="size-[34px]">
        <circle cx="18" cy="18" r="16.5" className="fill-primary" />
        <path d="M7 19h5l3-7 5 13 3-6h6" fill="none" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" className="stroke-bright" />
      </svg>
    </span>
  );
}

export function Brand({ name, compact = false, className }: BrandProps) {
  return (
    <span className={cn('flex items-center gap-[11px] whitespace-nowrap', className)}>
      <BrandMark />
      {compact ? <span className="sr-only">{name}</span> : <span className="text-2xl font-normal tracking-[-0.45px]">{name}</span>}
    </span>
  );
}
