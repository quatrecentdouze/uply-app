import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { landingContainer } from '@/app/pages/landing/container';
import { Brand } from '@/shared/ui/brand';
import { Button } from '@/shared/ui/button';

const anchorClass = 'text-sm text-muted-foreground transition-colors hover:text-foreground';

export function LandingHeader() {
  const { t } = useTranslation(['landing', 'common']);
  return (
    <header className="sticky top-0 z-40 border-b border-divider bg-page/90 backdrop-blur-md">
      <div className={`${landingContainer} flex h-[74px] items-center gap-3 sm:h-[92px] sm:gap-8`}>
        <Link to="/" aria-label={t('common:app.home')} className="rounded-[14px]">
          <Brand name={t('common:app.name')} className="[&>span:first-child]:size-10 [&>span:last-child]:text-xl sm:[&>span:first-child]:size-[45px] sm:[&>span:last-child]:text-[23px]" />
        </Link>
        <nav aria-label={t('landing:nav.label')} className="mx-auto hidden items-center gap-8 md:flex">
          <a href="#features" className={anchorClass}>
            {t('landing:nav.features')}
          </a>
          <a href="#faq" className={anchorClass}>
            {t('landing:nav.faq')}
          </a>
        </nav>
        <div className="ml-auto flex items-center gap-2 md:ml-0 sm:gap-3">
          <Button asChild variant="ghost" className="h-10 rounded-[10px] px-3 text-sm font-normal sm:px-4">
            <Link to="/login">{t('landing:nav.sign_in')}</Link>
          </Button>
          <Button asChild className="h-10 rounded-[10px] px-3 text-sm sm:px-4">
            <Link to="/register">{t('landing:nav.get_started')}</Link>
          </Button>
        </div>
      </div>
    </header>
  );
}
