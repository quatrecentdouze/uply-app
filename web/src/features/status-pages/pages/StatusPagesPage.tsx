import { zodResolver } from '@hookform/resolvers/zod';
import { Link, useNavigate } from '@tanstack/react-router';
import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useCreateStatusPage, useStatusPages } from '@/features/status-pages/api/statusPages';
import { useStatusPageTexts } from '@/features/status-pages/hooks/useStatusPageTexts';
import { createStatusPageBody, type NewStatusPageValues, newStatusPageSchema, slugFrom, type StatusPagesMeta } from '@/features/status-pages/model/statusPageForm';
import { failureOf } from '@/shared/api/errors';
import { reportFailure } from '@/shared/api/formFailure';
import type { StatusPage } from '@/shared/api/resources';
import { Button } from '@/shared/ui/button';
import { cellClass, DataTable, rowClass } from '@/shared/ui/data-table';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { FormAlert } from '@/shared/ui/form-alert';
import { FormField } from '@/shared/ui/form-field';
import { Plus } from '@/shared/ui/icons';
import { Skeleton } from '@/shared/ui/skeleton';
import { Pill, SurfaceCard } from '@/shared/ui/surface-card';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface StatusPagesPageProps {
  organizationId: string;
  meta: StatusPagesMeta | null;
  canManage: boolean;
}

const fields = ['title', 'slug'] as const;

function NewStatusPageForm({ organizationId, meta, onDone }: { organizationId: string; meta: StatusPagesMeta; onDone: () => void }) {
  const { t } = useTranslation(['status-pages', 'errors']);
  const texts = useStatusPageTexts();
  const navigate = useNavigate();
  const create = useCreateStatusPage(organizationId);
  const schema = useMemo(() => newStatusPageSchema(texts, meta), [texts, meta]);
  const form = useForm<NewStatusPageValues>({ resolver: zodResolver(schema), defaultValues: { title: '', slug: '' } });
  const [slugTouched, setSlugTouched] = useState(false);
  const { errors, isSubmitting } = form.formState;
  const title = form.register('title');
  const slug = form.register('slug');

  const submit = form.handleSubmit(async (values) => {
    try {
      const page = await create.mutateAsync(createStatusPageBody(values));
      onDone();
      await navigate({ to: '/status-pages/$statusPageId', params: { statusPageId: page.id } });
    } catch (error) {
      reportFailure(form.setError, failureOf(error), fields, (key) => t(key));
    }
  });

  return (
    <form noValidate onSubmit={(event) => void submit(event)} className="grid gap-5">
      {errors.root?.server?.message === undefined ? null : <FormAlert tone="alert">{errors.root.server.message}</FormAlert>}
      <FormField
        label={t('status-pages:form.title')}
        autoComplete="off"
        error={errors.title?.message}
        {...title}
        onChange={(event) => {
          void title.onChange(event);
          if (!slugTouched) {
            form.setValue('slug', slugFrom(event.target.value, meta.lengths.slug.max));
          }
        }}
      />
      <FormField
        label={t('status-pages:form.slug')}
        autoComplete="off"
        spellCheck={false}
        hint={t('status-pages:form.slug_hint')}
        error={errors.slug?.message}
        {...slug}
        onChange={(event) => {
          setSlugTouched(true);
          void slug.onChange(event);
        }}
      />
      <DialogFooter className="gap-2">
        <Button type="button" variant="outline" className="h-10 rounded-[9px]" onClick={onDone}>
          {t('status-pages:form.cancel')}
        </Button>
        <Button type="submit" disabled={isSubmitting} aria-busy={isSubmitting} className="h-10 rounded-[9px]">
          {t('status-pages:form.create')}
        </Button>
      </DialogFooter>
    </form>
  );
}

function StatusPageRow({ page }: { page: StatusPage }) {
  const { t } = useTranslation(['status-pages']);
  return (
    <tr className={rowClass}>
      <td className={`${cellClass} max-w-0 truncate pl-[18px]`}>
        <Link to="/status-pages/$statusPageId" params={{ statusPageId: page.id }} className="hover:text-primary">
          {page.title}
        </Link>
      </td>
      <td className={`${cellClass} truncate text-muted-foreground`}>{t('status-pages:list.path', { slug: page.slug })}</td>
      <td className={`${cellClass} whitespace-nowrap`}>{t('status-pages:list.monitors_count', { count: page.monitors.length })}</td>
      <td className={`${cellClass} pr-4 text-right`}>
        <Pill tone={page.is_published ? 'positive' : 'neutral'}>{page.is_published ? t('status-pages:status.published') : t('status-pages:status.draft')}</Pill>
      </td>
    </tr>
  );
}

export function StatusPagesPage({ organizationId, meta, canManage }: StatusPagesPageProps) {
  const { t } = useTranslation(['status-pages']);
  const query = useStatusPages(organizationId);
  const pages = query.data;
  const retry = retryOf(query);
  const [creating, setCreating] = useState(false);
  const writable = canManage && meta !== null;

  return (
    <SurfaceCard aria-labelledby="status-pages-title" className="px-2.5 pt-4 pb-3 sm:px-5">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="status-pages-title" className="text-[17px] font-light">
          {t('status-pages:list.title')}
        </h2>
        {writable ? (
          <Button
            type="button"
            className="h-10 rounded-[11px] px-4"
            onClick={() => {
              setCreating(true);
            }}
          >
            <Plus aria-hidden="true" />
            {t('status-pages:list.add')}
          </Button>
        ) : null}
      </div>
      {pages === undefined ? (
        retry === null ? (
          <Skeleton className="h-[160px] w-full" />
        ) : (
          <LoadError onRetry={retry} />
        )
      ) : pages.length === 0 ? (
        <p className="py-10 text-center text-muted-foreground">{t('status-pages:list.empty')}</p>
      ) : (
        <DataTable
          label={t('status-pages:list.table')}
          className="min-w-[640px] table-fixed"
          head={[
            { label: t('status-pages:list.page'), className: 'w-[34%]' },
            { label: t('status-pages:list.address'), className: 'w-[30%]' },
            { label: t('status-pages:list.monitors'), className: 'w-[18%]' },
            { label: t('status-pages:list.status'), className: 'w-[18%] text-right' },
          ]}
        >
          {pages.map((page) => (
            <StatusPageRow key={page.id} page={page} />
          ))}
        </DataTable>
      )}
      {meta === null ? null : (
        <Dialog open={creating} onOpenChange={setCreating}>
          <DialogContent className="rounded-[20px] border-2 border-line-strong bg-panel p-[25px] ring-0 sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle className="text-[23px] font-medium">{t('status-pages:form.title_create')}</DialogTitle>
              <DialogDescription>{t('status-pages:form.description_create')}</DialogDescription>
            </DialogHeader>
            {creating ? (
              <NewStatusPageForm
                organizationId={organizationId}
                meta={meta}
                onDone={() => {
                  setCreating(false);
                }}
              />
            ) : null}
          </DialogContent>
        </Dialog>
      )}
    </SurfaceCard>
  );
}
