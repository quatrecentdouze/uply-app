import { useNavigate } from '@tanstack/react-router';
import { useWorkspace } from '@/app/hooks/useWorkspace';
import { useMe } from '@/features/auth/api/session';
import { MembersPage } from '@/features/organizations/pages/MembersPage';
import { OrganizationSettingsPage } from '@/features/organizations/pages/OrganizationSettingsPage';
import { ProfilePage } from '@/features/organizations/pages/ProfilePage';
import { SecurityPage } from '@/features/organizations/pages/SecurityPage';
import { useMeta } from '@/shared/api/meta';

export function SettingsOrganizationRoute() {
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
}

export function SettingsMembersRoute() {
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
}

export function SettingsProfileRoute() {
  const me = useMe().data;
  const lengths = useMeta()?.organizations.lengths ?? null;
  return me === undefined || me === null ? null : <ProfilePage me={me} lengths={lengths} />;
}

export function SettingsSecurityRoute() {
  const me = useMe().data;
  const navigate = useNavigate();
  return me === undefined || me === null ? null : <SecurityPage me={me} onAccountDeleted={() => navigate({ to: '/login' })} />;
}
