import type { QueryClient } from '@tanstack/react-query';
import {
  createRootRouteWithContext,
  createRoute,
  createRouter,
  Outlet,
  redirect,
  type RouterHistory,
  useNavigate,
} from '@tanstack/react-router';
import { AppLayout } from '@/app/layouts/AppLayout';
import { useWorkspace } from '@/app/hooks/useWorkspace';
import { AuthLayout } from '@/app/layouts/AuthLayout';
import { SettingsLayout } from '@/app/layouts/SettingsLayout';
import { entryRedirect, homeOf, type RouteAccess, safeRedirect, sessionOf, targetHref } from '@/app/model/entryFlow';
import { DashboardPage } from '@/app/pages/DashboardPage';
import { AlertChannelsPage } from '@/features/alerting/pages/AlertChannelsPage';
import { IncidentDetailPage } from '@/features/incidents/pages/IncidentDetailPage';
import { IncidentsPage } from '@/features/incidents/pages/IncidentsPage';
import { MonitorDetailPage } from '@/app/pages/MonitorDetailPage';
import { MonitorsPage } from '@/app/pages/MonitorsPage';
import { StatusPageEditorPage } from '@/app/pages/StatusPageEditorPage';
import { NotFoundPage, StandaloneNotFoundPage } from '@/app/pages/NotFoundPage';
import { meQuery, useLogout, useMe } from '@/features/auth/api/session';
import { resetLinkOf } from '@/features/auth/model/forms';
import { ForgotPasswordPage } from '@/features/auth/pages/ForgotPasswordPage';
import { type LoginNotice, LoginPage } from '@/features/auth/pages/LoginPage';
import { RegisterPage } from '@/features/auth/pages/RegisterPage';
import { ResetPasswordPage } from '@/features/auth/pages/ResetPasswordPage';
import { TwoFactorChallengePage } from '@/features/auth/pages/TwoFactorChallengePage';
import { VerifyEmailPage } from '@/features/auth/pages/VerifyEmailPage';
import { organizationsQuery } from '@/features/organizations/api/organizations';
import type { InvitationViewer } from '@/features/organizations/model/organizations';
import { CreateOrganizationPage } from '@/features/organizations/pages/CreateOrganizationPage';
import { InvitationPage } from '@/features/organizations/pages/InvitationPage';
import { MembersPage } from '@/features/organizations/pages/MembersPage';
import { OrganizationSettingsPage } from '@/features/organizations/pages/OrganizationSettingsPage';
import { ProfilePage } from '@/features/organizations/pages/ProfilePage';
import { SecurityPage } from '@/features/organizations/pages/SecurityPage';
import { StatusPagesPage } from '@/features/status-pages/pages/StatusPagesPage';
import { dashboardSettings } from '@/features/monitors/model/settings';
import type { ApiClient } from '@/shared/api/client';
import { useMeta } from '@/shared/api/meta';
import type { Me } from '@/shared/api/resources';
import { useNow } from '@/shared/time/useNow';

export interface RouterContext {
  queryClient: QueryClient;
  apiClient: ApiClient;
}

declare module '@tanstack/react-router' {
  interface Register {
    router: AppRouter;
  }
}

async function guard(context: RouterContext, access: RouteAccess, href: string): Promise<void> {
  const me = await context.queryClient.query({ ...meQuery(context.apiClient), staleTime: 'static' });
  const target = entryRedirect(sessionOf(me), access, href);
  if (target !== null) {
    throw redirect({ href: targetHref(target) });
  }
}

interface RedirectSearch {
  redirect?: string;
}

function redirectSearch(search: Record<string, unknown>): RedirectSearch {
  const value = safeRedirect(search.redirect);
  return value === null ? {} : { redirect: value };
}

const loginNotices: readonly LoginNotice[] = ['password-reset'];

function loginSearch(search: Record<string, unknown>): RedirectSearch & { notice?: LoginNotice } {
  const notice = loginNotices.find((candidate) => candidate === search.notice);
  return notice === undefined ? redirectSearch(search) : { ...redirectSearch(search), notice };
}

function resetSearch(search: Record<string, unknown>): { token?: string; email?: string } {
  return {
    ...(typeof search.token === 'string' ? { token: search.token } : {}),
    ...(typeof search.email === 'string' ? { email: search.email } : {}),
  };
}

const home = '/dashboard';

const rootRoute = createRootRouteWithContext<RouterContext>()({
  component: Outlet,
  notFoundComponent: StandaloneNotFoundPage,
});

const homeRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/',
  beforeLoad: async ({ context }): Promise<never> => {
    const me = await context.queryClient.query({ ...meQuery(context.apiClient), staleTime: 'static' });
    throw redirect({ to: homeOf(sessionOf(me)) });
  },
});

const entryLayout = createRoute({ getParentRoute: () => rootRoute, id: 'entry', component: AuthLayout });

const loginRoute = createRoute({
  getParentRoute: () => entryLayout,
  path: 'login',
  validateSearch: loginSearch,
  beforeLoad: ({ context, location }) => guard(context, 'guest', location.href),
  component: function LoginRoute() {
    const search = loginRoute.useSearch();
    return <LoginPage redirectTo={search.redirect ?? home} notice={search.notice ?? null} />;
  },
});

const registerRoute = createRoute({
  getParentRoute: () => entryLayout,
  path: 'register',
  validateSearch: redirectSearch,
  beforeLoad: ({ context, location }) => guard(context, 'guest', location.href),
  component: function RegisterRoute() {
    return <RegisterPage redirectTo={registerRoute.useSearch().redirect ?? home} />;
  },
});

const forgotPasswordRoute = createRoute({
  getParentRoute: () => entryLayout,
  path: 'forgot-password',
  beforeLoad: ({ context, location }) => guard(context, 'guest', location.href),
  component: ForgotPasswordPage,
});

const resetPasswordRoute = createRoute({
  getParentRoute: () => entryLayout,
  path: 'reset-password',
  validateSearch: resetSearch,
  beforeLoad: ({ context, location }) => guard(context, 'guest', location.href),
  component: function ResetPasswordRoute() {
    return <ResetPasswordPage link={resetLinkOf(resetPasswordRoute.useSearch())} />;
  },
});

const twoFactorRoute = createRoute({
  getParentRoute: () => entryLayout,
  path: 'two-factor-challenge',
  validateSearch: redirectSearch,
  beforeLoad: ({ context, location }) => guard(context, 'guest', location.href),
  component: function TwoFactorRoute() {
    return <TwoFactorChallengePage redirectTo={twoFactorRoute.useSearch().redirect ?? home} />;
  },
});

const verifyEmailRoute = createRoute({
  getParentRoute: () => entryLayout,
  path: 'verify-email',
  beforeLoad: ({ context, location }) => guard(context, 'unverified', location.href),
  component: VerifyEmailPage,
});

const createOrganizationRoute = createRoute({
  getParentRoute: () => entryLayout,
  path: 'organizations/new',
  beforeLoad: ({ context, location }) => guard(context, 'verified', location.href),
  component: function CreateOrganizationRoute() {
    const memberships = useMe().data?.memberships.length ?? 0;
    return <CreateOrganizationPage canCancel={memberships > 0} />;
  },
});

function viewerOf(me: Me | null): InvitationViewer {
  if (me === null) {
    return { kind: 'guest' };
  }
  return me.email_verified_at === null ? { kind: 'unverified', email: me.email } : { kind: 'verified', email: me.email };
}

const invitationRoute = createRoute({
  getParentRoute: () => entryLayout,
  path: 'invitations/$token',
  beforeLoad: ({ context, location }) => guard(context, 'public', location.href),
  component: function InvitationRoute() {
    const { token } = invitationRoute.useParams();
    const me = useMe().data ?? null;
    const logout = useLogout();
    const navigate = useNavigate();
    const switchAccount = async () => {
      await logout.mutateAsync().catch(() => undefined);
      await navigate({ to: '/login', search: { redirect: `/invitations/${token}` } });
    };
    return <InvitationPage token={token} viewer={viewerOf(me)} onSwitchAccount={() => void switchAccount()} />;
  },
});

const appLayout = createRoute({
  getParentRoute: () => rootRoute,
  id: 'app',
  beforeLoad: ({ context, location }) => guard(context, 'member', location.href),
  loader: ({ context }) => context.queryClient.query({ ...organizationsQuery(context.apiClient), staleTime: 'static' }),
  component: AppLayout,
});

const pagesLayout = createRoute({
  getParentRoute: () => appLayout,
  id: 'pages',
  component: Outlet,
  notFoundComponent: NotFoundPage,
});

const dashboardRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'dashboard',
  component: DashboardPage,
});

const monitorsRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'monitors',
  component: MonitorsPage,
});
const monitorRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'monitors/$monitorId',
  component: function MonitorRoute() {
    return <MonitorDetailPage monitorId={monitorRoute.useParams().monitorId} />;
  },
});
const incidentsRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'incidents',
  component: function IncidentsRoute() {
    const { organizationId } = useWorkspace();
    const now = useNow(dashboardSettings.clock_tick_seconds);
    return <IncidentsPage organizationId={organizationId} now={now} />;
  },
});

const incidentRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'incidents/$incidentId',
  component: function IncidentRoute() {
    const { organizationId } = useWorkspace();
    const now = useNow(dashboardSettings.clock_tick_seconds);
    return <IncidentDetailPage organizationId={organizationId} incidentId={incidentRoute.useParams().incidentId} now={now} notFound={<NotFoundPage />} />;
  },
});
const statusPagesRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'status-pages',
  component: function StatusPagesRoute() {
    const { organizationId, can } = useWorkspace();
    const meta = useMeta()?.status_pages ?? null;
    return <StatusPagesPage organizationId={organizationId} meta={meta} canManage={can('status-pages.manage')} />;
  },
});

const statusPageRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'status-pages/$statusPageId',
  component: function StatusPageRoute() {
    return <StatusPageEditorPage statusPageId={statusPageRoute.useParams().statusPageId} />;
  },
});
const alertChannelsRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'alert-channels',
  component: function AlertChannelsRoute() {
    const { organizationId, can } = useWorkspace();
    const alerting = useMeta()?.alerting ?? null;
    return <AlertChannelsPage organizationId={organizationId} alerting={alerting} canManage={can('alert-channels.manage')} />;
  },
});
const settingsLayout = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'settings',
  component: SettingsLayout,
});

const settingsIndexRoute = createRoute({
  getParentRoute: () => settingsLayout,
  path: '/',
  beforeLoad: (): never => {
    throw redirect({ to: '/settings/organization' });
  },
});

const settingsOrganizationRoute = createRoute({
  getParentRoute: () => settingsLayout,
  path: 'organization',
  component: function SettingsOrganizationRoute() {
    const { organization, can } = useWorkspace();
    const navigate = useNavigate();
    const bounds = useMeta()?.organizations.lengths.organization_name ?? null;
    return (
      <OrganizationSettingsPage
        key={organization.id}
        organization={organization}
        nameBounds={bounds}
        canRename={can('organization.manage')}
        canDelete={can('organization.delete')}
        onDeleted={() => navigate({ to: '/dashboard' })}
      />
    );
  },
});

const settingsMembersRoute = createRoute({
  getParentRoute: () => settingsLayout,
  path: 'members',
  component: function SettingsMembersRoute() {
    const { organization, can } = useWorkspace();
    const navigate = useNavigate();
    const me = useMe().data;
    if (me === undefined || me === null) {
      return null;
    }
    return (
      <MembersPage
        key={organization.id}
        organizationId={organization.id}
        actorRole={organization.role}
        actorUserId={me.id}
        canManage={can('members.manage')}
        onLeft={() => navigate({ to: '/dashboard' })}
      />
    );
  },
});

const settingsProfileRoute = createRoute({
  getParentRoute: () => settingsLayout,
  path: 'profile',
  component: function SettingsProfileRoute() {
    const me = useMe().data;
    const lengths = useMeta()?.organizations.lengths ?? null;
    return me === undefined || me === null ? null : <ProfilePage me={me} lengths={lengths} />;
  },
});

const settingsSecurityRoute = createRoute({
  getParentRoute: () => settingsLayout,
  path: 'security',
  component: function SettingsSecurityRoute() {
    const me = useMe().data;
    const navigate = useNavigate();
    return me === undefined || me === null ? null : <SecurityPage me={me} onAccountDeleted={() => navigate({ to: '/login' })} />;
  },
});

const routeTree = rootRoute.addChildren([
  homeRoute,
  entryLayout.addChildren([
    loginRoute,
    registerRoute,
    forgotPasswordRoute,
    resetPasswordRoute,
    twoFactorRoute,
    verifyEmailRoute,
    createOrganizationRoute,
    invitationRoute,
  ]),
  appLayout.addChildren([
    pagesLayout.addChildren([
      dashboardRoute,
      monitorsRoute,
      monitorRoute,
      incidentsRoute,
      incidentRoute,
      statusPagesRoute,
      statusPageRoute,
      alertChannelsRoute,
      settingsLayout.addChildren([settingsIndexRoute, settingsOrganizationRoute, settingsMembersRoute, settingsProfileRoute, settingsSecurityRoute]),
    ]),
  ]),
]);

export function createAppRouter(context: RouterContext, history?: RouterHistory) {
  return createRouter({
    routeTree,
    context,
    defaultPreload: 'intent',
    scrollRestoration: true,
    ...(history === undefined ? {} : { history }),
  });
}

export type AppRouter = ReturnType<typeof createAppRouter>;
