import { useTranslation } from 'react-i18next';
import { usePublicStatusPage } from '@/features/status-pages/api/publicStatusPage';
import { PublicIncidentList } from '@/features/status-pages/components/PublicIncidentList';
import { PublicMonitorCard } from '@/features/status-pages/components/PublicMonitorCard';
import { PublicNotFound } from '@/features/status-pages/components/PublicNotFound';
import { StatusBanner } from '@/features/status-pages/components/StatusBanner';
import { overallStateOf } from '@/features/status-pages/model/publicPage';
import { publicPageSettings } from '@/features/status-pages/model/publicPageSettings';
import { isFailure } from '@/shared/api/errors';
import { useMeta } from '@/shared/api/meta';
import { LoadError } from '@/shared/ui/load-error';
import { Skeleton } from '@/shared/ui/skeleton';
import { useDocumentTitle } from '@/shared/ui/use-document-title';

export function PublicStatusPage({ slug }: { slug: string }) {
  const { t } = useTranslation(['status-pages', 'common']);
  const settings = useMeta()?.status_pages.public ?? null;
  const query = usePublicStatusPage(slug, settings?.cache_seconds ?? null);
  const page = query.data;
  useDocumentTitle(page === undefined ? null : t('common:titles.document', { page: page.title }));

  if (isFailure(query.error, 'not_found')) {
    return <PublicNotFound />;
  }
  if (page === undefined) {
    return query.isError ? (
      <LoadError
        onRetry={() => {
          void query.refetch();
        }}
      />
    ) : (
      <div aria-busy="true" aria-label={t('status-pages:public.loading')} className="grid gap-5">
        <Skeleton className="h-12 w-2/3" />
        <Skeleton className="h-[72px] w-full" />
        <Skeleton className="h-[140px] w-full" />
      </div>
    );
  }

  return (
    <div className="grid gap-5 sm:gap-6">
      <header>
        <h1 className="text-[28px] leading-tight font-normal tracking-[-0.65px] break-words sm:text-[36px] sm:tracking-[-1px]">{page.title}</h1>
        {page.description === null || page.description === '' ? null : <p className="mt-2 text-base whitespace-pre-line text-muted-foreground">{page.description}</p>}
      </header>
      <StatusBanner state={overallStateOf(page.overall_state)} />
      <section aria-labelledby="public-monitors-title">
        <h2 id="public-monitors-title" className="sr-only">
          {t('status-pages:public.monitors')}
        </h2>
        {page.monitors.length === 0 ? (
          <p className="rounded-card border-2 border-line bg-surface p-5 text-muted-foreground">{t('status-pages:public.no_monitors')}</p>
        ) : (
          <ul className="grid gap-3 sm:gap-4">
            {page.monitors.map((monitor) => (
              <PublicMonitorCard key={monitor.id} monitor={monitor} compactCount={publicPageSettings.compact_bars} />
            ))}
          </ul>
        )}
      </section>
      <PublicIncidentList incidents={page.incidents} days={settings?.incident_days ?? null} />
    </div>
  );
}
