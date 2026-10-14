import { useTranslation } from 'react-i18next';
import { landingContainer } from '@/app/pages/landing/container';
import type { TextKey } from '@/shared/i18n/textKey';
import { BellRing, Gauge, type LucideIcon, Plus } from '@/shared/ui/icons';

interface Step {
  icon: LucideIcon;
  title: TextKey<'landing'>;
  text: TextKey<'landing'>;
}

const steps: readonly Step[] = [
  { icon: Plus, title: 'landing:steps.add.title', text: 'landing:steps.add.text' },
  { icon: BellRing, title: 'landing:steps.alert.title', text: 'landing:steps.alert.text' },
  { icon: Gauge, title: 'landing:steps.share.title', text: 'landing:steps.share.text' },
];

export function LandingSteps() {
  const { t, i18n } = useTranslation(['landing']);
  const number = new Intl.NumberFormat(i18n.language, { minimumIntegerDigits: 2 });
  return (
    <section aria-labelledby="steps-title" className="border-y border-divider bg-panel/50 py-16 sm:py-20 lg:py-[114px]">
      <div className={landingContainer}>
        <div className="text-center">
          <h2 id="steps-title" className="text-[34px] leading-[1.15] font-normal tracking-[-1.2px] sm:text-[clamp(32px,3.4vw,46px)] sm:tracking-[-1.7px]">
            <span className="block">{t('landing:steps.title_start')}</span>
            <span className="block">{t('landing:steps.title_end')}</span>
          </h2>
          <p className="mx-auto mt-4 max-w-[420px] text-sm text-muted-foreground sm:mt-[18px] sm:text-[15px]">{t('landing:steps.description')}</p>
        </div>
        <ol className="mt-9 grid gap-4 md:mt-12 md:grid-cols-3 md:gap-5">
          {steps.map(({ icon: Icon, title, text }, index) => (
            <li key={title} className="flex flex-col rounded-card border border-line-strong bg-panel p-6 sm:p-7">
              <div className="flex items-center justify-between">
                <span aria-hidden="true" className="grid size-[41px] place-items-center rounded-[12px] border border-line-strong bg-linear-135 from-kbd to-surface-raised">
                  <Icon className="size-5" strokeWidth={1.5} />
                </span>
                <span aria-hidden="true" className="text-[34px] leading-none font-light tracking-[-1px] text-quiet">
                  {number.format(index + 1)}
                </span>
              </div>
              <h3 className="mt-6 text-lg font-normal">{t(title)}</h3>
              <p className="mt-2 text-sm leading-[1.8] text-muted-foreground">{t(text)}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}
