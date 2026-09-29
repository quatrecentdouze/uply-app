import type { ReactNode } from 'react';
import { Button } from '@/shared/ui/button';

export function SubmitButton({ pending, children }: { pending: boolean; children: ReactNode }) {
  return (
    <Button type="submit" disabled={pending} aria-busy={pending} className="h-11 w-full rounded-[9px] text-sm font-medium">
      {children}
    </Button>
  );
}
