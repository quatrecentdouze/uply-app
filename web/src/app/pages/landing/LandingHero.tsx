import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { Button } from '@/shared/ui/button';
import { ArrowRight, BellRing, Clock } from '@/shared/ui/icons';

const enter = 'motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-4 motion-safe:duration-700 motion-safe:fill-mode-both';

export function LandingHero() {
  const { t } = useTranslation(['landing']);
  return (
    <section aria-labelledby="hero-title" className="pt-10 text-center sm:pt-14 2xl:pt-[58px]">
      <p className={`inline-flex items-center gap-2.5 rounded-full border border-line-strong bg-surface-raised py-1.5 pr-3 pl-1.5 text-xs leading-5 text-quiet ${enter}`}>
        <span aria-hidden="true" className="size-[22px] rounded-full bg-linear-135 from-glow-start via-glow-gold to-glow-blue" />
        {t('landing:hero.pill')}
      </p>
      <h1 id="hero-title" className={`mx-auto mt-6 mb-5 text-[clamp(36px,9vw,52px)] leading-[1.1] font-normal tracking-[-1.9px] sm:text-[clamp(46px,5.5vw,76px)] sm:leading-[1.07] sm:tracking-[-3.2px] ${enter} motion-safe:delay-100`}>
        <span className="block">{t('landing:hero.title_start')}</span>
        <span className="block">{t('landing:hero.title_end')}</span>
      </h1>
      <p className={`mx-auto max-w-[540px] text-sm leading-[1.8] text-muted-foreground sm:text-[17px] sm:leading-[1.7] ${enter} motion-safe:delay-200`}>{t('landing:hero.description')}</p>
      <div className={`mt-7 flex flex-wrap justify-center gap-3 ${enter} motion-safe:delay-300`}>
        <Button asChild className="h-12 rounded-[10px] px-[22px] text-sm">
          <Link to="/register">
            {t('landing:nav.get_started')}
            <ArrowRight aria-hidden="true" />
          </Link>
        </Button>
        <Button asChild variant="secondary" className="h-12 rounded-[10px] border border-line-strong px-[22px] text-sm font-normal">
          <Link to="/login">{t('landing:nav.sign_in')}</Link>
        </Button>
      </div>
      <ul className={`mt-5 flex flex-wrap justify-center gap-x-5 gap-y-2 text-xs text-quiet ${enter} motion-safe:delay-500`}>
        <li className="flex items-center gap-1.5">
          <Clock className="size-3.5" aria-hidden="true" />
          {t('landing:hero.notes.intervals')}
        </li>
        <li className="flex items-center gap-1.5">
          <BellRing className="size-3.5" aria-hidden="true" />
          {t('landing:hero.notes.channels')}
        </li>
      </ul>
    </section>
  );
}
