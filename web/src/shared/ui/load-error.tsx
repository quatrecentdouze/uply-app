import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Button } from '@/shared/ui/button';
import { RotateCw } from '@/shared/ui/icons';

interface LoadErrorProps {
  onRetry: () => void;
  className?: string;
}

export function LoadError({ onRetry, className }: LoadErrorProps) {
  const { t } = useTranslation(['common']);
  return (
    <div role="alert" className={cn('flex flex-col items-center gap-3 py-6 text-center', className)}>
      <p className="text-muted-foreground">{t('common:load_error.message')}</p>
      <Button type="button" variant="outline" className="h-10 rounded-[11px] px-4 text-sm font-normal" onClick={onRetry}>
        <RotateCw aria-hidden="true" />
        {t('common:load_error.retry')}
      </Button>
    </div>
  );
}
