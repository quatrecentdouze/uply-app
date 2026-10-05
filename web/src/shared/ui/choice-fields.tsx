import { cn } from 'cn';
import { type ComponentProps, type ReactNode, useId } from 'react';
import { Label } from '@/shared/ui/label';
import { NativeSelect, NativeSelectOption } from '@/shared/ui/native-select';
import { Switch } from '@/shared/ui/switch';
import { Textarea } from '@/shared/ui/textarea';

export interface ChoiceOption {
  value: string;
  label: string;
}

interface FieldFrameProps {
  id: string;
  label: string;
  error: string | undefined;
  hint: ReactNode | undefined;
  className: string | undefined;
  children: ReactNode;
}

function FieldFrame({ id, label, error, hint, className, children }: FieldFrameProps) {
  return (
    <div className={cn('grid content-start gap-2', className)}>
      <Label htmlFor={id} className="text-sm font-normal text-muted-foreground">
        {label}
      </Label>
      {children}
      {hint === undefined ? null : (
        <p id={`${id}-hint`} className="text-sm text-faint">
          {hint}
        </p>
      )}
      {error === undefined ? null : (
        <p id={`${id}-error`} role="alert" className="text-sm text-alert">
          {error}
        </p>
      )}
    </div>
  );
}

function describedBy(id: string, error: string | undefined, hint: ReactNode | undefined): string | undefined {
  const ids = [error === undefined ? null : `${id}-error`, hint === undefined ? null : `${id}-hint`].filter((value) => value !== null);
  return ids.length === 0 ? undefined : ids.join(' ');
}

interface SelectFieldProps extends Omit<ComponentProps<'select'>, 'id' | 'size'> {
  label: string;
  options: readonly ChoiceOption[];
  error?: string | undefined;
  hint?: ReactNode;
}

export function SelectField({ label, options, error, hint, className, ...select }: SelectFieldProps) {
  const id = useId();
  return (
    <FieldFrame id={id} label={label} error={error} hint={hint} className={className}>
      <NativeSelect
        id={id}
        aria-invalid={error === undefined ? undefined : true}
        aria-describedby={describedBy(id, error, hint)}
        className="w-full [&_select]:h-11 [&_select]:rounded-[11px] [&_select]:border-field-line [&_select]:bg-field [&_select]:px-3.5 [&_select]:text-base"
        {...select}
      >
        {options.map((option) => (
          <NativeSelectOption key={option.value} value={option.value}>
            {option.label}
          </NativeSelectOption>
        ))}
      </NativeSelect>
    </FieldFrame>
  );
}

interface TextareaFieldProps extends Omit<ComponentProps<'textarea'>, 'id'> {
  label: string;
  error?: string | undefined;
  hint?: ReactNode;
}

export function TextareaField({ label, error, hint, className, ...textarea }: TextareaFieldProps) {
  const id = useId();
  return (
    <FieldFrame id={id} label={label} error={error} hint={hint} className={className}>
      <Textarea
        id={id}
        aria-invalid={error === undefined ? undefined : true}
        aria-describedby={describedBy(id, error, hint)}
        className="min-h-24 rounded-[11px] border-field-line bg-field px-3.5 text-base"
        {...textarea}
      />
    </FieldFrame>
  );
}

interface SwitchFieldProps {
  label: string;
  description?: string;
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
  disabled?: boolean;
  className?: string;
}

export function SwitchField({ label, description, checked, onCheckedChange, disabled, className }: SwitchFieldProps) {
  const id = useId();
  return (
    <div className={cn('flex items-center justify-between gap-4 rounded-[13px] border-2 border-divider bg-surface-raised px-4 py-3', className)}>
      <div className="grid gap-0.5">
        <Label htmlFor={id} className="text-base font-normal">
          {label}
        </Label>
        {description === undefined ? null : (
          <p id={`${id}-description`} className="text-sm text-muted-foreground">
            {description}
          </p>
        )}
      </div>
      <Switch
        id={id}
        checked={checked}
        onCheckedChange={onCheckedChange}
        disabled={disabled}
        aria-describedby={description === undefined ? undefined : `${id}-description`}
      />
    </div>
  );
}
