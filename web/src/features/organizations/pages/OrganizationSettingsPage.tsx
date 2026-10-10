import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useDeleteOrganization, useRenameOrganization } from '@/features/organizations/api/organizations';
import { PasswordConfirmDialog } from '@/features/organizations/components/PasswordConfirmDialog';
import { type OrganizationValues, organizationSchema, roleTextKeys } from '@/features/organizations/model/organizations';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import type { Bounds } from '@/shared/api/meta';
import type { Organization } from '@/shared/api/resources';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { Button } from '@/shared/ui/button';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';
import { Trash2 } from '@/shared/ui/icons';
import { CardHeading, SurfaceCard } from '@/shared/ui/surface-card';

interface OrganizationSettingsPageProps {
  organization: Organization;
  nameBounds: Bounds | null;
  canRename: boolean;
  canDelete: boolean;
  onDeleted: () => Promise<void>;
}

const fields = ['name'] as const;

export function OrganizationSettingsPage({ organization, nameBounds, canRename, canDelete, onDeleted }: OrganizationSettingsPageProps) {
  const { t } = useTranslation(['organizations', 'errors']);
  const validation = useValidationTexts();
  const rename = useRenameOrganization(organization.id);
  const remove = useDeleteOrganization(organization.id);
  const schema = useMemo(() => organizationSchema(validation, nameBounds), [validation, nameBounds]);
  const form = useForm<OrganizationValues>({ resolver: zodResolver(schema), values: { name: organization.name } });
  const [saved, setSaved] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const { errors, isSubmitting, isDirty } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    setSaved(false);
    try {
      await rename.mutateAsync(values.name.trim());
      setSaved(true);
    } catch (error) {
      reportFailure(form.setError, failureOf(error), fields, (key) => t(key));
    }
  });

  return (
    <div className="grid gap-4 2xl:gap-5">
      <SurfaceCard aria-labelledby="organization-title" className="p-4 sm:p-5">
        <CardHeading id="organization-title" title={t('organizations:settings.organization.title')} />
        <form noValidate onSubmit={(event) => void submit(event)} className="mt-4 grid max-w-xl gap-5">
          {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
          {saved && !isDirty ? <FormAlert tone="positive">{t('organizations:settings.organization.saved')}</FormAlert> : null}
          <FormField label={t('organizations:create.name')} autoComplete="organization" disabled={!canRename} error={errors.name?.message} {...form.register('name')} />
          <dl className="grid gap-3 sm:grid-cols-2">
            <div className="grid gap-0.5">
              <dt className="text-sm text-muted-foreground">{t('organizations:settings.organization.slug')}</dt>
              <dd>{organization.slug}</dd>
            </div>
            <div className="grid gap-0.5">
              <dt className="text-sm text-muted-foreground">{t('organizations:settings.organization.your_role')}</dt>
              <dd>{t(roleTextKeys[organization.role])}</dd>
            </div>
          </dl>
          {canRename ? (
            <Button type="submit" disabled={!isDirty || isSubmitting} aria-busy={isSubmitting} className="h-10 w-fit rounded-[11px]">
              {t('organizations:settings.organization.save')}
            </Button>
          ) : null}
        </form>
      </SurfaceCard>
      {canDelete ? (
        <SurfaceCard aria-labelledby="danger-title" className="border-alert/30 p-4 sm:p-5">
          <CardHeading id="danger-title" title={t('organizations:settings.organization.delete_title')} />
          <p className="mt-2 max-w-2xl text-muted-foreground">{t('organizations:settings.organization.delete_description')}</p>
          <Button
            type="button"
            variant="destructive"
            className="mt-4 h-10 rounded-[11px]"
            onClick={() => {
              setDeleting(true);
            }}
          >
            <Trash2 aria-hidden="true" />
            {t('organizations:settings.organization.delete')}
          </Button>
        </SurfaceCard>
      ) : null}
      <PasswordConfirmDialog
        open={deleting}
        onOpenChange={setDeleting}
        texts={{
          title: t('organizations:settings.organization.delete_confirm_title', { name: organization.name }),
          description: t('organizations:settings.organization.delete_confirm_description'),
          confirm: t('organizations:settings.organization.delete'),
          cancel: t('organizations:settings.cancel'),
        }}
        onConfirm={async (password) => {
          await remove.mutateAsync(password);
          setDeleting(false);
          await onDeleted();
        }}
      />
    </div>
  );
}
