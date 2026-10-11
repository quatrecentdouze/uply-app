import { zodResolver } from '@hookform/resolvers/zod';
import { Link, useNavigate } from '@tanstack/react-router';
import { useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useCreateOrganization } from '@/features/organizations/api/organizations';
import { rememberOrganization } from '@/features/organizations/hooks/currentOrganization';
import { type OrganizationValues, organizationSchema } from '@/features/organizations/model/organizations';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import { useMeta } from '@/shared/api/meta';
import { useValidationTexts } from '@/shared/i18n/useValidationTexts';
import { Button } from '@/shared/ui/button';
import { EntryCard } from '@/shared/ui/entry-card';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';

const fields = ['name'] as const;

export function CreateOrganizationPage({ canCancel }: { canCancel: boolean }) {
  const { t } = useTranslation(['organizations', 'errors']);
  const validation = useValidationTexts();
  const navigate = useNavigate();
  const create = useCreateOrganization();
  const bounds = useMeta()?.organizations.lengths.organization_name ?? null;
  const schema = useMemo(() => organizationSchema(validation, bounds), [validation, bounds]);
  const form = useForm<OrganizationValues>({ resolver: zodResolver(schema), defaultValues: { name: '' } });
  const { errors, isSubmitting } = form.formState;

  const submit = form.handleSubmit(async (values) => {
    try {
      const organization = await create.mutateAsync(values.name);
      rememberOrganization(organization.id);
      await navigate({ to: '/dashboard' });
    } catch (error) {
      reportFailure(form.setError, failureOf(error), fields, (key) => t(key));
    }
  });

  return (
    <EntryCard
      title={t('organizations:create.title')}
      description={t('organizations:create.description')}
      footer={
        canCancel ? (
          <Link to="/dashboard" className="text-primary hover:underline">
            {t('organizations:create.cancel')}
          </Link>
        ) : undefined
      }
    >
      <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
        {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
        <FormField label={t('organizations:create.name')} autoComplete="organization" error={errors.name?.message} {...form.register('name')} />
        <Button type="submit" disabled={isSubmitting} aria-busy={isSubmitting} className="h-11 w-full rounded-[9px] text-sm font-medium">
          {t('organizations:create.submit')}
        </Button>
      </form>
    </EntryCard>
  );
}
