import type { ReactNode } from 'react';

interface EntryCardProps {
  title: string;
  description?: ReactNode;
  children?: ReactNode;
  footer?: ReactNode;
}

export function EntryCard({ title, description, children, footer }: EntryCardProps) {
  return (
    <section className="w-full rounded-card border-2 border-line bg-surface p-7 sm:p-8">
      <h1 className="text-[28px] leading-tight font-normal tracking-[-0.65px]">{title}</h1>
      {description === undefined ? null : <p className="mt-2 text-base text-muted-foreground">{description}</p>}
      {children === undefined ? null : <div className="mt-7">{children}</div>}
      {footer === undefined ? null : <div className="mt-6 border-t-2 border-line pt-5 text-sm text-muted-foreground">{footer}</div>}
    </section>
  );
}
