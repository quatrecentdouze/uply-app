import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import type { TextKey } from '@/shared/i18n/textKey';
import { ArrowRight, Plus } from '@/shared/ui/icons';

interface FaqItem {
  question: TextKey<'landing'>;
  answer: TextKey<'landing'>;
}

const faqItems: readonly FaqItem[] = [
  { question: 'landing:faq.items.checks.question', answer: 'landing:faq.items.checks.answer' },
  { question: 'landing:faq.items.intervals.question', answer: 'landing:faq.items.intervals.answer' },
  { question: 'landing:faq.items.alerts.question', answer: 'landing:faq.items.alerts.answer' },
  { question: 'landing:faq.items.status_pages.question', answer: 'landing:faq.items.status_pages.answer' },
  { question: 'landing:faq.items.teams.question', answer: 'landing:faq.items.teams.answer' },
  { question: 'landing:faq.items.security.question', answer: 'landing:faq.items.security.answer' },
];

export function LandingFaq() {
  const { t } = useTranslation(['landing']);
  return (
    <section id="faq" aria-labelledby="faq-title" className="grid scroll-mt-24 gap-8 py-16 sm:py-20 lg:grid-cols-[0.85fr_1.25fr] lg:gap-20 lg:py-[114px]">
      <div>
        <h2 id="faq-title" className="text-[34px] leading-[1.15] font-normal tracking-[-1.2px] sm:text-[clamp(32px,3.4vw,46px)] sm:tracking-[-1.7px]">
          <span className="block">{t('landing:faq.title_start')}</span>
          <span className="block">{t('landing:faq.title_end')}</span>
        </h2>
        <p className="mt-5 max-w-[300px] text-sm text-muted-foreground">{t('landing:faq.description')}</p>
        <Link to="/register" className="mt-6 inline-flex min-h-10 items-center gap-2 text-sm text-foreground hover:text-primary">
          {t('landing:faq.cta')}
          <ArrowRight className="size-4" aria-hidden="true" />
        </Link>
      </div>
      <div className="border-t border-line-strong">
        {faqItems.map(({ question, answer }, index) => (
          <details key={question} open={index === 0} className="group border-b border-line-strong">
            <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-5 py-5 text-sm sm:text-base [&::-webkit-details-marker]:hidden">
              {t(question)}
              <Plus className="size-[18px] shrink-0 text-quiet transition-transform group-open:rotate-45 group-open:text-primary" aria-hidden="true" />
            </summary>
            <p className="pr-7 pb-6 text-sm leading-[1.9] text-muted-foreground">{t(answer)}</p>
          </details>
        ))}
      </div>
    </section>
  );
}
