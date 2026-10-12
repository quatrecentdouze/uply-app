import type { QueryClient } from '@tanstack/react-query';
import {
  createRootRouteWithContext,
  createRoute,
  createRouter,
  lazyRouteComponent,
  Outlet,
  redirect,
  type RouterHistory,
  useNavigate,
} from '@tanstack/react-router';
import { AuthLayout } from '@/app/layouts/AuthLayout';
import { entryRedirect, type RouteAccess, safeRedirect, sessionOf, targetHref } from '@/app/model/entryFlow';
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
import type { ApiClient } from '@/shared/api/client';
import type { Me } from '@/shared/api/resources';

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

const landingRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/',
  beforeLoad: ({ context, location }) => guard(context, 'guest', location.href),
  component: lazyRouteComponent(() => import('@/app/pages/landing/LandingPage'), 'LandingPage'),
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
  component: lazyRouteComponent(() => import('@/app/layouts/AppLayout'), 'AppLayout'),
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
  component: lazyRouteComponent(() => import('@/app/pages/DashboardPage'), 'DashboardPage'),
});

const monitorsRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'monitors',
  component: lazyRouteComponent(() => import('@/app/pages/MonitorsPage'), 'MonitorsPage'),
});

const monitorRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'monitors/$monitorId',
  component: lazyRouteComponent(() => import('@/app/routes/MonitorRoutes'), 'MonitorRoute'),
});

const incidentsRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'incidents',
  component: lazyRouteComponent(() => import('@/app/routes/IncidentRoutes'), 'IncidentsRoute'),
});

const incidentRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'incidents/$incidentId',
  component: lazyRouteComponent(() => import('@/app/routes/IncidentRoutes'), 'IncidentRoute'),
});

const statusPagesRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'status-pages',
  component: lazyRouteComponent(() => import('@/app/routes/StatusPageRoutes'), 'StatusPagesRoute'),
});

const statusPageRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'status-pages/$statusPageId',
  component: lazyRouteComponent(() => import('@/app/routes/StatusPageRoutes'), 'StatusPageRoute'),
});

const alertChannelsRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'alert-channels',
  component: lazyRouteComponent(() => import('@/app/routes/AlertChannelRoutes'), 'AlertChannelsRoute'),
});

const settingsLayout = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'settings',
  component: lazyRouteComponent(() => import('@/app/layouts/SettingsLayout'), 'SettingsLayout'),
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
  component: lazyRouteComponent(() => import('@/app/routes/SettingsRoutes'), 'SettingsOrganizationRoute'),
});

const settingsMembersRoute = createRoute({
  getParentRoute: () => settingsLayout,
  path: 'members',
  component: lazyRouteComponent(() => import('@/app/routes/SettingsRoutes'), 'SettingsMembersRoute'),
});

const settingsProfileRoute = createRoute({
  getParentRoute: () => settingsLayout,
  path: 'profile',
  component: lazyRouteComponent(() => import('@/app/routes/SettingsRoutes'), 'SettingsProfileRoute'),
});

const settingsSecurityRoute = createRoute({
  getParentRoute: () => settingsLayout,
  path: 'security',
  component: lazyRouteComponent(() => import('@/app/routes/SettingsRoutes'), 'SettingsSecurityRoute'),
});

const routeTree = rootRoute.addChildren([
  landingRoute,
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
