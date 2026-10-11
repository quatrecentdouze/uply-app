import { Link } from '@tanstack/react-router';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { badgeText, type NavigationAction, navigationActionLabels, navigationGroups, type NavigationItem } from '@/app/model/navigation';
import { pageTitleKeys, type PageId } from '@/app/model/pages';
import { OrganizationSwitcher } from '@/features/organizations/components/OrganizationSwitcher';
import { BrandMark } from '@/shared/ui/brand';
import { Activity, BellRing, CircleHelp, Gauge, LayoutGrid, LogOut, type LucideIcon, PanelLeft, Search, Settings, Siren } from '@/shared/ui/icons';

const pageIcons: Record<PageId, LucideIcon> = {
  dashboard: LayoutGrid,
  monitors: Activity,
  incidents: Siren,
  status_pages: Gauge,
  alert_channels: BellRing,
  settings: Settings,
  help: CircleHelp,
};

const actionIcons: Record<NavigationAction, LucideIcon> = {
  help: CircleHelp,
  logout: LogOut,
};

export interface SidebarContentProps {
  collapsed: boolean;
  openIncidents: number | null;
  onToggleCollapsed: (() => void) | null;
  onSearch: () => void;
  onAction: (action: NavigationAction) => void;
  onNavigate: () => void;
}

const itemClass =
  'relative flex h-[49px] items-center gap-[13px] rounded-[14px] px-[17px] text-lg leading-[22px] text-sidebar-foreground transition-colors hover:bg-surface-raised hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring';

function NavigationEntry({ item, collapsed, openIncidents, onAction, onNavigate }: { item: NavigationItem } & Omit<SidebarContentProps, 'onToggleCollapsed' | 'onSearch'>) {
  const { t } = useTranslation(['common']);
  if (item.kind === 'action') {
    const Icon = actionIcons[item.action];
    const label = t(navigationActionLabels[item.action]);
    return (
      <button
        type="button"
        onClick={() => {
          onAction(item.action);
        }}
        aria-label={collapsed ? label : undefined}
        className={cn(itemClass, collapsed && 'justify-center px-0')}
      >
        <Icon className="size-[22px] shrink-0" strokeWidth={1.35} aria-hidden="true" />
        {collapsed ? null : <span>{label}</span>}
      </button>
    );
  }
  const Icon = pageIcons[item.page];
  const label = t(pageTitleKeys[item.page]);
  const badge = item.badge === 'open_incidents' ? badgeText(openIncidents) : null;
  return (
    <Link
      to={item.to}
      onClick={onNavigate}
      aria-label={collapsed ? label : undefined}
      className={cn(
        itemClass,
        'data-[status=active]:bg-secondary data-[status=active]:text-foreground data-[status=active]:hover:bg-accent',
        collapsed && 'justify-center px-0',
      )}
    >
      <Icon className="size-[22px] shrink-0" strokeWidth={1.35} aria-hidden="true" />
      {collapsed ? null : <span>{label}</span>}
      {badge === null || collapsed ? null : (
        <span
          aria-label={t('common:shell.open_incidents', { count: openIncidents ?? 0 })}
          className="ml-auto grid h-6 min-w-6 place-items-center rounded-full bg-linear-135 from-glow-orange via-glow-gold to-glow-blue px-1.5 text-xs font-normal text-ink"
        >
          {badge}
        </span>
      )}
    </Link>
  );
}

export function SidebarContent(props: SidebarContentProps) {
  const { t } = useTranslation(['common']);
  const { collapsed, onToggleCollapsed, onSearch } = props;
  return (
    <div className={cn('flex h-full flex-col overflow-y-auto pt-[18px] pb-[19px] [scrollbar-width:thin]', collapsed ? 'px-[18px]' : 'pr-[19px] pl-[18px] lg:px-4 2xl:pr-[19px] 2xl:pl-[18px]')}>
      <div className="flex h-[49px] shrink-0 items-center gap-[11px] whitespace-nowrap">
        {collapsed && onToggleCollapsed !== null ? (
          <button type="button" onClick={onToggleCollapsed} aria-label={t('common:shell.expand')} className="rounded-[14px]">
            <BrandMark />
          </button>
        ) : (
          <Link to="/dashboard" aria-label={t('common:app.home')} onClick={props.onNavigate} className="rounded-[14px]">
            <BrandMark />
          </Link>
        )}
        {collapsed ? null : <span className="text-2xl font-normal tracking-[-0.45px] lg:text-xl 2xl:text-2xl">{t('common:app.name')}</span>}
        {collapsed || onToggleCollapsed === null ? null : (
          <button
            type="button"
            onClick={onToggleCollapsed}
            aria-label={t('common:shell.collapse')}
            title={t('common:shell.collapse')}
            className="ml-auto grid size-[25px] place-items-center rounded-md bg-kbd text-muted-foreground"
          >
            <PanelLeft className="size-[17px]" aria-hidden="true" />
          </button>
        )}
      </div>
      {collapsed ? null : (
        <button
          type="button"
          onClick={onSearch}
          className="mt-[26px] flex h-11 shrink-0 items-center gap-2 rounded-[15px] border-2 border-field-line bg-field pr-2 pl-3.5 text-left text-quiet"
        >
          <Search className="size-5" strokeWidth={1.8} aria-hidden="true" />
          <span className="flex-1">{t('common:shell.search')}</span>
          <span className="flex gap-1" aria-hidden="true">
            <kbd className="grid h-[23px] min-w-[22px] place-items-center rounded-[5px] bg-kbd font-sans px-1 text-sm text-muted-foreground">{t('common:shortcuts.ctrl')}</kbd>
            <kbd className="grid h-[23px] w-[22px] place-items-center rounded-[5px] bg-kbd font-sans text-sm text-muted-foreground">{t('common:shortcuts.k')}</kbd>
          </span>
        </button>
      )}
      <nav aria-label={t('common:shell.navigation')} className="mt-[26px] flex flex-col gap-8">
        {navigationGroups.map((group) => (
          <section key={group.id}>
            {collapsed ? null : <h2 className="mb-[9px] ml-[17px] text-sm leading-[17px] font-light tracking-wide text-faint uppercase">{t(group.label)}</h2>}
            <div className="flex flex-col gap-[3px]">
              {group.items.map((item) => (
                <NavigationEntry key={item.kind === 'link' ? item.to : item.action} item={item} {...props} />
              ))}
            </div>
          </section>
        ))}
      </nav>
      <div className="mt-auto shrink-0 pt-8">
        <OrganizationSwitcher compact={collapsed} />
      </div>
    </div>
  );
}
