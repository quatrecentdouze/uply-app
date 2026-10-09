import type { ComponentProps, ReactNode } from 'react';
import { useId } from 'react';
import { cn } from 'cn';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';

interface FormFieldProps extends Omit<ComponentProps<typeof Input>, 'id'> {
  label: string;
  error?: string | undefined;
  hint?: ReactNode;
  trailing?: ReactNode;
}

export function FormField({ label, error, hint, trailing, className, ...input }: FormFieldProps) {
  const id = useId();
  const errorId = `${id}-error`;
  const hintId = `${id}-hint`;
  const describedBy = [error === undefined ? null : errorId, hint === undefined ? null : hintId].filter((value) => value !== null).join(' ');
  return (
    <div className={cn('grid content-start gap-2', className)}>
      <div className="flex items-center justify-between gap-3">
        <Label htmlFor={id} className="text-sm font-normal text-muted-foreground">
          {label}
        </Label>
        {trailing}
      </div>
      <Input
        id={id}
        aria-invalid={error === undefined ? undefined : true}
        aria-describedby={describedBy === '' ? undefined : describedBy}
        className="h-11 rounded-[11px] border-field-line bg-field px-3.5 text-base"
        {...input}
      />
      {hint === undefined ? null : (
        <p id={hintId} className="text-sm text-faint">
          {hint}
        </p>
      )}
      {error === undefined ? null : (
        <p id={errorId} role="alert" className="text-sm text-alert">
          {error}
        </p>
      )}
    </div>
  );
}
