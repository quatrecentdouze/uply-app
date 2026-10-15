import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useChangeRole, useInvitations, useInvite, useLeaveOrganization, useMembers, useRemoveMember, useRevokeInvitation } from '@/features/organizations/api/organizations';
import { assignableRoles, canChangeMember, defaultInviteRole, type InviteValues, inviteSchema, isLastOwner, sortedMembers } from '@/features/organizations/model/members';
import { roleTextKeys } from '@/features/organizations/model/organizations';
import { errorTextKey, failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import type { Invitation, Member, Role } from '@/shared/api/resources';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { formatDate } from '@/shared/time/format';
import { Button } from '@/shared/ui/button';
import { SelectField } from '@/shared/ui/choice-fields';
import { ConfirmDialog } from '@/shared/ui/confirm-dialog';
import { cellClass, DataTable, rowClass, secondaryCell } from '@/shared/ui/data-table';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';
import { UserPlus } from '@/shared/ui/icons';
import { NativeSelect, NativeSelectOption } from '@/shared/ui/native-select';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';
import { useConfirmAction } from '@/shared/ui/use-confirm-action';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface MembersPageProps {
  organizationId: string;
  actorRole: Role;
  actorUserId: string;
  canManage: boolean;
  onLeft: () => Promise<void>;
}

const dateFormat: Intl.DateTimeFormatOptions = { dateStyle: 'medium' };

const inviteFields = ['email', 'role'] as const;

function InviteForm({ organizationId, actorRole }: { organizationId: string; actorRole: Role }) {
  const { t } = useTranslation(['organizations', 'errors']);
  const validation = useValidationTexts();
  const invite = useInvite(organizationId);
  const schema = useMemo(() => inviteSchema(validation, actorRole), [validation, actorRole]);
  const form = useForm<InviteValues>({ resolver: zodResolver(schema), defaultValues: { email: '', role: defaultInviteRole(actorRole) } });
  const [sent, setSent] = useState<string | null>(null);
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    setSent(null);
    try {
      await invite.mutateAsync({ email: values.email.trim(), role: values.role });
      setSent(values.email.trim());
      form.reset({ email: '', role: values.role });
    } catch (error) {
      reportFailure(form.setError, failureOf(error), inviteFields, (key) => t(key));
    }
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-4">
      {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
      {sent === null ? null : <FormAlert tone="positive">{t('organizations:settings.members.invited', { email: sent })}</FormAlert>}
      <div className="flex flex-wrap items-start gap-3">
        <FormField className="min-w-64 flex-[2]" label={t('organizations:settings.members.invite_email')} type="email" autoComplete="off" error={errors.email?.message} {...form.register('email')} />
        <SelectField
          className="min-w-40 flex-1"
          label={t('organizations:settings.members.role')}
          options={assignableRoles(actorRole).map((role) => ({ value: role, label: t(roleTextKeys[role]) }))}
          error={errors.role?.message}
          {...form.register('role')}
        />
        <Button type="submit" disabled={isSubmitting} aria-busy={isSubmitting} className="mt-7 h-11 rounded-[11px]">
          <UserPlus aria-hidden="true" />
          {t('organizations:settings.members.invite')}
        </Button>
      </div>
    </form>
  );
}

interface MemberRowProps {
  member: Member;
  members: readonly Member[];
  actorRole: Role;
  actorUserId: string;
  canManage: boolean;
  onRole: (member: Member, role: Role) => void;
  onRemove: (member: Member) => void;
  onLeave: (member: Member) => void;
}

function MemberRow({ member, members, actorRole, actorUserId, canManage, onRole, onRemove, onLeave }: MemberRowProps) {
  const { t, i18n } = useTranslation(['organizations']);
  const self = member.user.id === actorUserId;
  const editable = canManage && canChangeMember(actorRole, member) && !isLastOwner(member, members);
  return (
    <tr className={rowClass}>
      <td className={`${cellClass} max-w-0 pl-[18px]`}>
        <span className="grid gap-0.5">
          <span className="truncate">{self ? t('organizations:settings.members.you', { name: member.user.name }) : member.user.name}</span>
          <span className="truncate text-sm text-muted-foreground">{member.user.email}</span>
        </span>
      </td>
      <td className={cellClass}>
        {editable ? (
          <NativeSelect
            aria-label={t('organizations:settings.members.role_of', { name: member.user.name })}
            value={member.role}
            className="[&_select]:h-10 md:[&_select]:h-9 [&_select]:rounded-[10px] [&_select]:border-field-line [&_select]:bg-field"
            onChange={(event) => {
              const role = assignableRoles(actorRole).find((candidate) => candidate === event.target.value);
              if (role !== undefined) {
                onRole(member, role);
              }
            }}
          >
            {assignableRoles(actorRole).map((role) => (
              <NativeSelectOption key={role} value={role}>
                {t(roleTextKeys[role])}
              </NativeSelectOption>
            ))}
          </NativeSelect>
        ) : (
          <Pill tone="neutral">{t(roleTextKeys[member.role])}</Pill>
        )}
      </td>
      <td className={`${cellClass} ${secondaryCell} whitespace-nowrap text-muted-foreground`}>{member.joined_at === null ? '' : formatDate(member.joined_at, { locale: i18n.language }, dateFormat)}</td>
      <td className={`${cellClass} pr-4 text-right`}>
        {self && isLastOwner(member, members) ? (
          <span className="text-sm text-faint">{t('organizations:settings.members.last_owner')}</span>
        ) : self ? (
          <Button
            type="button"
            variant="outline"
            className="h-10 rounded-[10px] md:h-9"
            onClick={() => {
              onLeave(member);
            }}
          >
            {t('organizations:settings.members.leave')}
          </Button>
        ) : editable ? (
          <Button
            type="button"
            variant="destructive"
            className="h-10 rounded-[10px] md:h-9"
            onClick={() => {
              onRemove(member);
            }}
          >
            {t('organizations:settings.members.remove')}
          </Button>
        ) : null}
      </td>
    </tr>
  );
}

function InvitationRow({ invitation, onRevoke }: { invitation: Invitation; onRevoke: ((invitation: Invitation) => void) | null }) {
  const { t, i18n } = useTranslation(['organizations']);
  return (
    <tr className={rowClass}>
      <td className={`${cellClass} max-w-0 truncate pl-[18px]`}>{invitation.email}</td>
      <td className={cellClass}>
        <Pill tone="pending">{t(roleTextKeys[invitation.role])}</Pill>
      </td>
      <td className={`${cellClass} ${secondaryCell} truncate text-muted-foreground`}>{invitation.invited_by?.name ?? ''}</td>
      <td className={`${cellClass} ${secondaryCell} whitespace-nowrap text-muted-foreground`}>
        {invitation.expires_at === null ? '' : formatDate(invitation.expires_at, { locale: i18n.language }, dateFormat)}
      </td>
      <td className={`${cellClass} pr-4 text-right`}>
        {onRevoke === null ? null : (
          <Button
            type="button"
            variant="outline"
            className="h-10 rounded-[10px] md:h-9"
            onClick={() => {
              onRevoke(invitation);
            }}
          >
            {t('organizations:settings.members.revoke')}
          </Button>
        )}
      </td>
    </tr>
  );
}

export function MembersPage({ organizationId, actorRole, actorUserId, canManage, onLeft }: MembersPageProps) {
  const { t } = useTranslation(['organizations', 'errors']);
  const memberQuery = useMembers(organizationId);
  const invitationQuery = useInvitations(organizationId, canManage);
  const members = memberQuery.data;
  const invitations = invitationQuery.data;
  const retryMembers = retryOf(memberQuery);
  const retryInvitations = retryOf(invitationQuery);
  const changeRole = useChangeRole(organizationId);
  const remove = useRemoveMember(organizationId);
  const leave = useLeaveOrganization(organizationId);
  const revoke = useRevokeInvitation(organizationId);
  const [notice, setNotice] = useState<string | null>(null);
  const removal = useConfirmAction(async (member: Member) => {
    await remove.mutateAsync(member.id);
  });
  const leaving = useConfirmAction(async (member: Member) => {
    await leave.mutateAsync(member.id);
    await onLeft();
  });
  const revoking = useConfirmAction(async (invitation: Invitation) => {
    await revoke.mutateAsync(invitation.id);
  });

  const onRole = (member: Member, role: Role) => {
    setNotice(null);
    changeRole.mutate(
      { membershipId: member.id, role },
      {
        onError: (error) => {
          setNotice(t(errorTextKey(failureOf(error))));
        },
      },
    );
  };

  const list = members === undefined ? undefined : sortedMembers(members);

  return (
    <div className="grid gap-4 2xl:gap-5">
      <SurfaceCard aria-labelledby="members-title" className="px-2.5 pt-4 pb-3 sm:px-5">
        <div className="mb-[13px]">
          <CardHeading id="members-title" title={t('organizations:settings.members.title')} />
        </div>
        {notice === null ? null : (
          <div className="mb-3">
            <FormAlert tone="alert">{notice}</FormAlert>
          </div>
        )}
        {list === undefined ? (
          retryMembers === null ? (
            <Skeleton className="h-[140px] w-full" />
          ) : (
            <LoadError onRetry={retryMembers} />
          )
        ) : (
          <DataTable
            label={t('organizations:settings.members.table')}
            className="table-fixed md:min-w-[640px]"
            head={[
              { label: t('organizations:settings.members.member'), className: 'w-[36%] md:w-[42%]' },
              { label: t('organizations:settings.members.role'), className: 'w-[32%] md:w-[20%]' },
              { label: t('organizations:settings.members.joined'), className: 'w-[18%]', secondary: true },
              { label: t('organizations:settings.members.actions'), className: 'w-[32%] md:w-[20%]', hidden: true },
            ]}
          >
            {list.map((member) => (
              <MemberRow
                key={member.id}
                member={member}
                members={list}
                actorRole={actorRole}
                actorUserId={actorUserId}
                canManage={canManage}
                onRole={onRole}
                onRemove={removal.ask}
                onLeave={leaving.ask}
              />
            ))}
          </DataTable>
        )}
      </SurfaceCard>
      {canManage ? (
        <SurfaceCard aria-labelledby="invitations-title" className="grid gap-4 px-2.5 pt-4 pb-3 sm:px-5">
          <CardHeading id="invitations-title" title={t('organizations:settings.members.invitations')} />
          <InviteForm organizationId={organizationId} actorRole={actorRole} />
          {invitations === undefined ? (
            retryInvitations === null ? (
              <Skeleton className="h-[80px] w-full" />
            ) : (
              <LoadError onRetry={retryInvitations} />
            )
          ) : invitations.length === 0 ? (
            <p className="pb-2 text-muted-foreground">{t('organizations:settings.members.no_invitations')}</p>
          ) : (
            <DataTable
              label={t('organizations:settings.members.invitations_table')}
              className="table-fixed md:min-w-[640px]"
              head={[
                { label: t('organizations:settings.members.email'), className: 'w-[50%] md:w-[34%]' },
                { label: t('organizations:settings.members.role'), className: 'w-[26%] md:w-[16%]' },
                { label: t('organizations:settings.members.invited_by'), className: 'w-[20%]', secondary: true },
                { label: t('organizations:settings.members.expires'), className: 'w-[16%]', secondary: true },
                { label: t('organizations:settings.members.actions'), className: 'w-[24%] md:w-[14%]', hidden: true },
              ]}
            >
              {invitations.map((invitation) => (
                <InvitationRow key={invitation.id} invitation={invitation} onRevoke={canChangeMember(actorRole, invitation) ? revoking.ask : null} />
              ))}
            </DataTable>
          )}
        </SurfaceCard>
      ) : null}
      <ConfirmDialog
        {...removal.dialog}
        texts={{
          title: t('organizations:settings.members.remove_title', { name: removal.target?.user.name ?? '' }),
          description: t('organizations:settings.members.remove_description'),
          confirm: t('organizations:settings.members.remove'),
          cancel: t('organizations:settings.cancel'),
        }}
      />
      <ConfirmDialog
        {...leaving.dialog}
        texts={{
          title: t('organizations:settings.members.leave_title'),
          description: t('organizations:settings.members.leave_description'),
          confirm: t('organizations:settings.members.leave'),
          cancel: t('organizations:settings.cancel'),
        }}
      />
      <ConfirmDialog
        {...revoking.dialog}
        texts={{
          title: t('organizations:settings.members.revoke_title', { email: revoking.target?.email ?? '' }),
          description: t('organizations:settings.members.revoke_description'),
          confirm: t('organizations:settings.members.revoke'),
          cancel: t('organizations:settings.cancel'),
        }}
      />
    </div>
  );
}
