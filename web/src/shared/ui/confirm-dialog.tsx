import type { ReactNode } from 'react';
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import { Button } from '@/shared/ui/button';
import { FormAlert } from '@/shared/ui/form-alert';

export interface ConfirmTexts {
  title: string;
  description: string;
  confirm: string;
  cancel: string;
}

interface ConfirmDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  texts: ConfirmTexts;
  busy: boolean;
  error: string | null;
  onConfirm: () => void;
  canConfirm?: boolean;
  children?: ReactNode;
}

export function ConfirmDialog({ open, onOpenChange, texts, busy, error, onConfirm, canConfirm = true, children }: ConfirmDialogProps) {
  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent className="rounded-[20px] border-2 border-line-strong bg-panel p-[25px] ring-0 sm:max-w-[460px]">
        <form
          noValidate
          className="grid gap-5"
          onSubmit={(event) => {
            event.preventDefault();
            if (canConfirm && !busy) {
              onConfirm();
            }
          }}
        >
          <AlertDialogHeader>
            <AlertDialogTitle className="text-[21px] font-medium">{texts.title}</AlertDialogTitle>
            <AlertDialogDescription className="text-base text-muted-foreground">{texts.description}</AlertDialogDescription>
          </AlertDialogHeader>
          {error === null ? null : <FormAlert tone="alert">{error}</FormAlert>}
          {children}
          <AlertDialogFooter className="mx-0 mb-0 border-t-0 bg-transparent p-0">
            <AlertDialogCancel type="button" disabled={busy} className="h-10 rounded-[9px]">
              {texts.cancel}
            </AlertDialogCancel>
            <Button type="submit" variant="destructive" disabled={busy || !canConfirm} aria-busy={busy} className="h-10 rounded-[9px]">
              {texts.confirm}
            </Button>
          </AlertDialogFooter>
        </form>
      </AlertDialogContent>
    </AlertDialog>
  );
}
