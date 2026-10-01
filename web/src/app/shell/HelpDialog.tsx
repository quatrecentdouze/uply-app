import { useTranslation } from 'react-i18next';
import type { TextKey } from '@/shared/i18n/textKey';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/shared/ui/dialog';

const shortcuts: readonly { keys: readonly TextKey<'common'>[]; action: TextKey<'common'> }[] = [
  { keys: ['common:shortcuts.ctrl', 'common:shortcuts.k'], action: 'common:shortcuts.search' },
  { keys: ['common:shortcuts.question'], action: 'common:shortcuts.help' },
  { keys: ['common:shortcuts.escape'], action: 'common:shortcuts.close' },
];

export function HelpDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation(['common']);
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="rounded-[20px] border-line-strong bg-panel p-[25px] sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle className="text-[23px] font-medium">{t('common:shortcuts.title')}</DialogTitle>
          <DialogDescription>{t('common:shortcuts.description')}</DialogDescription>
        </DialogHeader>
        <dl className="mt-2 grid gap-3">
          {shortcuts.map((shortcut) => (
            <div key={shortcut.action} className="flex items-center justify-between gap-4">
              <dt className="text-muted-foreground">{t(shortcut.action)}</dt>
              <dd className="flex gap-1">
                {shortcut.keys.map((key) => (
                  <kbd key={key} className="grid h-[23px] min-w-[22px] place-items-center rounded-[5px] bg-kbd font-sans px-1.5 text-sm text-muted-foreground">
                    {t(key)}
                  </kbd>
                ))}
              </dd>
            </div>
          ))}
        </dl>
      </DialogContent>
    </Dialog>
  );
}
