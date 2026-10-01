import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { initialsOf } from '@/app/model/navigation';
import type { Me } from '@/shared/api/resources';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/ui/dropdown-menu';
import { Bell, ChevronsUpDown, CircleHelp, LogOut, Menu, User } from '@/shared/ui/icons';

interface TopBarProps {
  title: string;
  me: Me;
  hasOpenIncident: boolean;
  onMenu: () => void;
  onHelp: () => void;
  onLogout: () => void;
}

const headerButton = 'relative grid size-10 place-items-center rounded-[12px] border-[1.5px] border-line-strong bg-linear-135 from-surface-raised to-panel md:size-[47px] md:rounded-[15px]';

function ProfileMenu({ me, onLogout }: { me: Me; onLogout: () => void }) {
  const { t } = useTranslation(['common']);
  return (
    <DropdownMenu>
      <DropdownMenuTrigger aria-label={t('common:shell.open_profile', { name: me.name })} className="flex items-center gap-[13px] rounded-full text-left outline-none focus-visible:ring-2 focus-visible:ring-ring">
        <span aria-hidden="true" className="grid size-10 place-items-center rounded-full bg-linear-135 from-glow-start via-glow-gold to-glow-blue text-base font-medium text-ink md:size-[47px]">
          {initialsOf(me.name, me.email)}
        </span>
        <span className="hidden flex-col gap-[3px] xl:flex">
          <span className="text-lg leading-[21px]">{me.name}</span>
          <span className="text-base leading-[19px] tracking-[-0.3px] text-sidebar-foreground">{me.email}</span>
        </span>
        <ChevronsUpDown className="hidden h-6 w-4 text-muted-foreground md:block" aria-hidden="true" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-56">
        <DropdownMenuLabel className="grid gap-0.5">
          <span className="text-sm font-normal text-foreground">{me.name}</span>
          <span className="text-xs font-light text-muted-foreground">{me.email}</span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <Link to="/settings/profile">
            <User aria-hidden="true" />
            {t('common:shell.profile')}
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem onSelect={onLogout}>
          <LogOut aria-hidden="true" />
          {t('common:shell.logout')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export function TopBar({ title, me, hasOpenIncident, onMenu, onHelp, onLogout }: TopBarProps) {
  const { t } = useTranslation(['common']);
  return (
    <header className="flex h-[78px] items-center justify-between gap-3 border-b-2 border-divider px-3 sm:px-[18px] md:h-topbar lg:px-5 2xl:pr-[26px] 2xl:pl-[25px]">
      <button type="button" onClick={onMenu} aria-label={t('common:shell.open_navigation')} className="grid h-[35px] w-8 place-items-center lg:hidden">
        <Menu className="size-6" aria-hidden="true" />
      </button>
      <h1 className="mr-auto truncate text-[19px] font-normal tracking-[-0.4px] sm:text-2xl lg:text-[25px] 2xl:text-[28px] 2xl:tracking-[-0.65px]">{title}</h1>
      <div className="flex items-center gap-2 sm:gap-3 2xl:gap-[21px]">
        <div className="flex gap-1.5 md:gap-[9px]">
          <button type="button" onClick={onHelp} aria-label={t('common:shell.help')} className={`${headerButton} hidden sm:grid`}>
            <CircleHelp className="size-[26px]" strokeWidth={1.6} aria-hidden="true" />
          </button>
          <Link
            to="/incidents"
            aria-label={hasOpenIncident ? t('common:shell.notifications_open') : t('common:shell.notifications')}
            className={headerButton}
          >
            <Bell className="size-[26px]" strokeWidth={1.6} aria-hidden="true" />
            {hasOpenIncident ? <span data-testid="open-incident-dot" className="absolute top-[11px] right-3 size-2 rounded-full border border-panel bg-alert" /> : null}
          </Link>
        </div>
        <ProfileMenu me={me} onLogout={onLogout} />
      </div>
    </header>
  );
}
