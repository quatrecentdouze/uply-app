import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { Brand, BrandMark } from '@/shared/ui/brand';
import { Button } from '@/shared/ui/button';
import { ArrowRight } from '@/shared/ui/icons';

export function LandingClosing() {
  const { t } = useTranslation(['landing']);
  return (
    <section aria-labelledby="closing-title" className="relative isolate overflow-hidden rounded-card border border-line-strong bg-linear-130 from-surface-raised via-surface to-panel px-6 py-12 text-center sm:px-8 sm:py-[70px]">
      <BrandMark className="mx-auto mb-6 size-[55px] rounded-[17px] [&>svg]:size-[37px]" />
      <h2 id="closing-title" className="text-[32px] leading-[1.15] font-normal tracking-[-1px] sm:text-[42px] sm:tracking-[-1.5px]">
        <span className="block">{t('landing:closing.title_start')}</span>
        <span className="block">{t('landing:closing.title_end')}</span>
      </h2>
      <p className="mx-auto mt-4 max-w-[420px] text-sm text-muted-foreground">{t('landing:closing.description')}</p>
      <Button asChild className="mt-7 h-12 rounded-[10px] px-[22px] text-sm">
        <Link to="/register">
          {t('landing:nav.get_started')}
          <ArrowRight aria-hidden="true" />
        </Link>
      </Button>
    </section>
  );
}

export function LandingFooter() {
  const { t } = useTranslation(['landing', 'common']);
  return (
    <footer className="pt-12 pb-6 sm:pt-[60px]">
      <div className="flex flex-col gap-7 pb-7 sm:flex-row sm:items-center sm:justify-between sm:pb-8">
        <div>
          <Brand name={t('common:app.name')} className="[&>span:first-child]:size-[35px] [&>span:first-child]:rounded-[11px] [&>span:last-child]:text-[19px] [&_svg]:size-[25px]" />
          <p className="mt-2.5 text-xs text-quiet">{t('landing:footer.tagline')}</p>
        </div>
        <nav aria-label={t('landing:footer.label')} className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
          <a href="#features" className="flex min-h-10 items-center hover:text-foreground">
            {t('landing:nav.features')}
          </a>
          <a href="#faq" className="flex min-h-10 items-center hover:text-foreground">
            {t('landing:nav.faq')}
          </a>
          <Link to="/login" className="flex min-h-10 items-center hover:text-foreground">
            {t('landing:nav.sign_in')}
          </Link>
          <Link to="/register" className="flex min-h-10 items-center hover:text-foreground">
            {t('landing:nav.get_started')}
          </Link>
        </nav>
      </div>
      <div className="flex flex-col gap-2 border-t border-divider pt-5 text-xs text-quiet sm:flex-row sm:justify-between">
        <span>{t('landing:footer.license')}</span>
        <span className="flex items-center gap-2">
          <span aria-hidden="true" className="size-1.5 rounded-full bg-positive" />
          {t('landing:footer.sample')}
        </span>
      </div>
    </footer>
  );
}
