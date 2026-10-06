import type { QueryClient } from '@tanstack/react-query';
import {
  createRootRouteWithContext,
  createRoute,
  createRouter,
  notFound,
  Outlet,
  redirect,
  type RouterHistory,
  useNavigate,
} from '@tanstack/react-router';
import { AppLayout } from '@/app/layouts/AppLayout';
import { AuthLayout } from '@/app/layouts/AuthLayout';
import { entryRedirect, type RouteAccess, safeRedirect, sessionOf, targetHref } from '@/app/model/entryFlow';
import { DashboardPage } from '@/app/pages/DashboardPage';
import { MonitorsPage } from '@/app/pages/MonitorsPage';
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

const home = '/';

const rootRoute = createRootRouteWithContext<RouterContext>()({
  component: Outlet,
  notFoundComponent: StandaloneNotFoundPage,
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
  path: '/',
  component: DashboardPage,
});

function nextMilestone<const Path extends string>(path: Path) {
  return createRoute({
    getParentRoute: () => pagesLayout,
    path,
    beforeLoad: (): never => {
      throw notFound({ routeId: pagesLayout.id });
    },
  });
}

const monitorsRoute = createRoute({
  getParentRoute: () => pagesLayout,
  path: 'monitors',
  component: MonitorsPage,
});
const monitorRoute = nextMilestone('monitors/$monitorId');
const incidentsRoute = nextMilestone('incidents');
const incidentRoute = nextMilestone('incidents/$incidentId');
const statusPagesRoute = nextMilestone('status-pages');
const statusPageRoute = nextMilestone('status-pages/$statusPageId');
const alertChannelsRoute = nextMilestone('alert-channels');
const settingsOrganizationRoute = nextMilestone('settings/organization');
const settingsMembersRoute = nextMilestone('settings/members');
const settingsProfileRoute = nextMilestone('settings/profile');
const settingsSecurityRoute = nextMilestone('settings/security');

const routeTree = rootRoute.addChildren([
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
      settingsOrganizationRoute,
      settingsMembersRoute,
      settingsProfileRoute,
      settingsSecurityRoute,
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
