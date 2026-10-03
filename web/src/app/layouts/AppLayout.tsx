import { useQuery } from '@tanstack/react-query';
import { Navigate, Outlet, useLocation, useNavigate } from '@tanstack/react-router';
import { cn } from 'cn';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type NavigationAction, shortcutOf } from '@/app/model/navigation';
import { pageOfPath, pageTitleKeys } from '@/app/model/pages';
import { CommandPalette } from '@/app/shell/CommandPalette';
import { HelpDialog } from '@/app/shell/HelpDialog';
import { SidebarContent } from '@/app/shell/SidebarContent';
import { TopBar } from '@/app/shell/TopBar';
import { useLogout, useMe } from '@/features/auth/api/session';
import { useOpenIncidentCount } from '@/features/incidents/api/incidents';
import { useOrganizations } from '@/features/organizations/api/organizations';
import { CurrentOrganizationProvider } from '@/features/organizations/components/CurrentOrganizationProvider';
import { useCurrentOrganization } from '@/features/organizations/hooks/currentOrganization';
import { useApiClient } from '@/shared/api/apiClientContext';
import { metaQuery } from '@/shared/api/meta';
import { useOrganizationRealtime } from '@/shared/realtime/useOrganizationRealtime';
import { browserStore, storageKeys } from '@/shared/storage/storage';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/shared/ui/sheet';

function isTyping(target: EventTarget | null): boolean {
  return target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
}

function usePageTitle(): string {
  const { t } = useTranslation(['common']);
  const page = pageOfPath(useLocation({ select: (location) => location.pathname }));
  return page === null ? t('common:not_found.title') : t(pageTitleKeys[page]);
}

function Shell() {
  const { t } = useTranslation(['common']);
  const client = useApiClient();
  const navigate = useNavigate();
  const me = useMe().data;
  const logout = useLogout();
  const { organization } = useCurrentOrganization();
  const connection = useQuery(metaQuery(client)).data?.realtime ?? null;
  const openIncidents = useOpenIncidentCount(organization.id);
  const title = usePageTitle();
  const [collapsed, setCollapsed] = useState(() => browserStore.read(storageKeys.sidebarCollapsed) === 'true');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [helpOpen, setHelpOpen] = useState(false);

  useOrganizationRealtime({ connection, client, organizationId: organization.id });

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      const shortcut = shortcutOf({ key: event.key, ctrlKey: event.ctrlKey, metaKey: event.metaKey, altKey: event.altKey, typing: isTyping(event.target) });
      if (shortcut === 'palette') {
        event.preventDefault();
        setPaletteOpen((open) => !open);
      } else if (shortcut === 'help') {
        event.preventDefault();
        setHelpOpen(true);
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => {
      window.removeEventListener('keydown', onKeyDown);
    };
  }, []);

  const toggleCollapsed = useCallback(() => {
    setCollapsed((current) => {
      browserStore.write(storageKeys.sidebarCollapsed, current ? null : 'true');
      return !current;
    });
  }, []);

  const signOut = useCallback(() => {
    void logout
      .mutateAsync()
      .catch(() => undefined)
      .then(() => navigate({ to: '/login' }));
  }, [logout, navigate]);

  const onAction = useCallback(
    (action: NavigationAction) => {
      setDrawerOpen(false);
      if (action === 'help') {
        setHelpOpen(true);
      } else {
        signOut();
      }
    },
    [signOut],
  );

  if (me === undefined || me === null) {
    return null;
  }

  const sidebar = {
    openIncidents,
    onSearch: () => {
      setDrawerOpen(false);
      setPaletteOpen(true);
    },
    onAction,
  };

  return (
    <div className="min-h-svh bg-page">
      <aside
        aria-label={t('common:shell.sidebar')}
        className={cn(
          'fixed inset-y-0 left-0 z-20 hidden border-r-2 border-divider bg-sidebar lg:block',
          collapsed ? 'w-sidebar-collapsed' : 'w-sidebar-narrow xl:w-sidebar-compact 2xl:w-sidebar',
        )}
      >
        <SidebarContent {...sidebar} collapsed={collapsed} onToggleCollapsed={toggleCollapsed} onNavigate={() => undefined} />
      </aside>
      <Sheet open={drawerOpen} onOpenChange={setDrawerOpen}>
        <SheetContent side="left" showCloseButton={false} className="w-[280px] border-r-2 border-divider bg-sidebar p-0 sm:max-w-[280px]">
          <SheetTitle className="sr-only">{t('common:shell.navigation')}</SheetTitle>
          <SheetDescription className="sr-only">{t('common:shell.navigation_description')}</SheetDescription>
          <SidebarContent
            {...sidebar}
            collapsed={false}
            onToggleCollapsed={null}
            onNavigate={() => {
              setDrawerOpen(false);
            }}
          />
        </SheetContent>
      </Sheet>
      <div className={cn('min-w-0', collapsed ? 'lg:ml-sidebar-collapsed' : 'lg:ml-sidebar-narrow xl:ml-sidebar-compact 2xl:ml-sidebar')}>
        <TopBar
          title={title}
          me={me}
          hasOpenIncident={(openIncidents ?? 0) > 0}
          onMenu={() => {
            setDrawerOpen(true);
          }}
          onHelp={() => {
            setHelpOpen(true);
          }}
          onLogout={signOut}
        />
        <main className="mx-auto max-w-[1800px] px-3 py-4 sm:px-[18px] sm:py-5 lg:px-5 2xl:pt-[25px] 2xl:pr-6 2xl:pb-[23px] 2xl:pl-[25px]">
          <Outlet />
        </main>
      </div>
      <CommandPalette open={paletteOpen} organizationId={organization.id} onOpenChange={setPaletteOpen} />
      <HelpDialog open={helpOpen} onOpenChange={setHelpOpen} />
    </div>
  );
}

export function AppLayout() {
  const organizations = useOrganizations();
  if (!organizations.isSuccess) {
    return null;
  }
  return (
    <CurrentOrganizationProvider organizations={organizations.data} fallback={<Navigate to="/organizations/new" />}>
      <Shell />
    </CurrentOrganizationProvider>
  );
}
