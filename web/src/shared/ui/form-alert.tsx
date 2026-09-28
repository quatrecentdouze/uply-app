import { CircleAlert, CircleCheck } from 'lucide-react';
import { cn } from 'cn';

interface FormAlertProps {
  tone: 'alert' | 'positive';
  children: string;
}

export function FormAlert({ tone, children }: FormAlertProps) {
  const Icon = tone === 'alert' ? CircleAlert : CircleCheck;
  return (
    <div
      role={tone === 'alert' ? 'alert' : 'status'}
      className={cn(
        'flex items-start gap-2.5 rounded-[11px] border px-3.5 py-3 text-sm',
        tone === 'alert' ? 'border-alert/30 bg-alert-soft text-alert' : 'border-positive/30 bg-positive-soft text-positive',
      )}
    >
      <Icon className="mt-px size-4 shrink-0" aria-hidden="true" />
      <span>{children}</span>
    </div>
  );
}
