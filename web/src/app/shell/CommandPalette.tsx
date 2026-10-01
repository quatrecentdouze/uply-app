import { useNavigate } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { navigationLinks } from '@/app/model/navigation';
import { pageTitleKeys } from '@/app/model/pages';
import { useMonitorList } from '@/features/monitors/api/monitors';
import { Command, CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/shared/ui/command';
import { Activity } from '@/shared/ui/icons';

interface CommandPaletteProps {
  open: boolean;
  organizationId: string;
  onOpenChange: (open: boolean) => void;
}

const paletteMonitors = { perPage: 100 } as const;

export function CommandPalette({ open, organizationId, onOpenChange }: CommandPaletteProps) {
  const { t } = useTranslation(['common']);
  const navigate = useNavigate();
  const monitors = useMonitorList(organizationId, paletteMonitors).data?.data ?? [];

  const go = (action: () => Promise<void>) => {
    onOpenChange(false);
    void action();
  };

  return (
    <CommandDialog open={open} onOpenChange={onOpenChange} title={t('common:palette.title')} description={t('common:palette.description')}>
      <Command>
        <CommandInput placeholder={t('common:palette.placeholder')} />
        <CommandList>
          <CommandEmpty>{t('common:palette.empty')}</CommandEmpty>
          <CommandGroup heading={t('common:palette.pages')}>
            {navigationLinks().map((link) => (
              <CommandItem
                key={link.to}
                value={t(pageTitleKeys[link.page])}
                onSelect={() => {
                  go(() => navigate({ to: link.to }));
                }}
              >
                {t(pageTitleKeys[link.page])}
              </CommandItem>
            ))}
          </CommandGroup>
          {monitors.length === 0 ? null : (
            <CommandGroup heading={t('common:palette.monitors')}>
              {monitors.map((monitor) => (
                <CommandItem
                  key={monitor.id}
                  value={`${monitor.name} ${monitor.url}`}
                  onSelect={() => {
                    go(() => navigate({ to: '/monitors/$monitorId', params: { monitorId: monitor.id } }));
                  }}
                >
                  <Activity aria-hidden="true" />
                  <span className="truncate">{monitor.name}</span>
                  <span className="ml-auto truncate text-xs text-muted-foreground">{monitor.url}</span>
                </CommandItem>
              ))}
            </CommandGroup>
          )}
        </CommandList>
      </Command>
    </CommandDialog>
  );
}
