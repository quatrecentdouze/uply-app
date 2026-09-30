import { Link, useNavigate } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { useAcceptInvitation, useInvitation } from '@/features/organizations/api/organizations';
import { rememberOrganization } from '@/features/organizations/hooks/currentOrganization';
import { type InvitationViewer, invitationStep, roleTextKeys } from '@/features/organizations/model/organizations';
import { errorTextKey, failureOf } from '@/shared/api/errors';
import type { PublicInvitation } from '@/shared/api/resources';
import { formatDateTime } from '@/shared/time/format';
import { Button } from '@/shared/ui/button';
import { EntryCard } from '@/shared/ui/entry-card';
import { FormAlert } from '@/shared/ui/form-alert';
import { Skeleton } from '@/shared/ui/skeleton';

interface InvitationPageProps {
  token: string;
  viewer: InvitationViewer;
  onSwitchAccount: () => void;
}

const primaryButton = 'h-11 w-full rounded-[9px] text-sm font-medium';

function InvitationActions({ token, viewer, invitation, onSwitchAccount }: InvitationPageProps & { invitation: PublicInvitation }) {
  const { t } = useTranslation(['organizations', 'errors']);
  const navigate = useNavigate();
  const accept = useAcceptInvitation(token);
  const redirect = `/invitations/${token}`;
  const step = invitationStep(viewer, invitation);

  const acceptInvitation = async () => {
    const organization = await accept.mutateAsync().catch(() => null);
    if (organization !== null) {
      rememberOrganization(organization.id);
      await navigate({ to: '/' });
    }
  };

  switch (step) {
    case 'sign_in':
      return (
        <div className="grid gap-3">
          <Button asChild className={primaryButton}>
            <Link to="/login" search={{ redirect }}>
              {t('organizations:invitation.sign_in')}
            </Link>
          </Button>
          <Button asChild variant="outline" className={primaryButton}>
            <Link to="/register" search={{ redirect }}>
              {t('organizations:invitation.register')}
            </Link>
          </Button>
        </div>
      );
    case 'verify_email':
      return (
        <div className="grid gap-4">
          <FormAlert tone="alert">{t('organizations:invitation.verify_first')}</FormAlert>
          <Button asChild className={primaryButton}>
            <Link to="/verify-email">{t('organizations:invitation.verify_link')}</Link>
          </Button>
        </div>
      );
    case 'switch_account':
      return (
        <div className="grid gap-4">
          <FormAlert tone="alert">{t('organizations:invitation.wrong_account', { email: viewer.kind === 'guest' ? '' : viewer.email })}</FormAlert>
          <Button type="button" variant="outline" className={primaryButton} onClick={onSwitchAccount}>
            {t('organizations:invitation.switch_account')}
          </Button>
        </div>
      );
    case 'accept':
      return (
        <div className="grid gap-4">
          {accept.isError ? <FormAlert tone="alert">{t(errorTextKey(failureOf(accept.error)))}</FormAlert> : null}
          <Button type="button" className={primaryButton} disabled={accept.isPending} aria-busy={accept.isPending} onClick={() => void acceptInvitation()}>
            {t('organizations:invitation.accept')}
          </Button>
        </div>
      );
  }
}

export function InvitationPage(props: InvitationPageProps) {
  const { t, i18n } = useTranslation(['organizations', 'errors']);
  const lookup = useInvitation(props.token);

  if (lookup.isPending) {
    return (
      <EntryCard title={t('organizations:invitation.loading')}>
        <Skeleton className="h-24 w-full" />
      </EntryCard>
    );
  }
  if (lookup.isError) {
    return <EntryCard title={t('organizations:invitation.unavailable')} description={t(errorTextKey(failureOf(lookup.error)))} />;
  }
  if (lookup.data.kind !== 'pending') {
    return (
      <EntryCard
        title={t('organizations:invitation.unavailable')}
        description={lookup.data.kind === 'expired' ? t('errors:codes.invitation_expired') : t('organizations:invitation.missing')}
        footer={
          <Link to="/" className="text-primary hover:underline">
            {t('organizations:invitation.home')}
          </Link>
        }
      />
    );
  }

  const { invitation } = lookup.data;
  const role = t(roleTextKeys[invitation.role]);
  return (
    <EntryCard
      title={t('organizations:invitation.title', { organization: invitation.organization.name })}
      description={
        invitation.invited_by === null
          ? t('organizations:invitation.description_without_inviter', { email: invitation.email, role })
          : t('organizations:invitation.description', { inviter: invitation.invited_by.name, email: invitation.email, role })
      }
      footer={
        invitation.expires_at === null
          ? undefined
          : t('organizations:invitation.expires', { date: formatDateTime(invitation.expires_at, { locale: i18n.language }) })
      }
    >
      <InvitationActions {...props} invitation={invitation} />
    </EntryCard>
  );
}
