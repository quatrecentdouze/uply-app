import { Link, Outlet } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { settingsSections } from '@/app/model/navigation';

export function SettingsLayout() {
  const { t } = useTranslation(['organizations']);
  return (
    <div className="grid gap-4 2xl:gap-5">
      <nav aria-label={t('organizations:settings.tabs.label')} className="flex flex-wrap items-center gap-[5px]">
        {settingsSections.map((section) => (
          <Link
            key={section.to}
            to={section.to}
            className="flex h-10 items-center rounded-[22px] md:h-[35px] px-[17px] text-base text-muted-foreground hover:text-foreground data-[status=active]:bg-bright data-[status=active]:text-ink"
          >
            {t(section.label)}
          </Link>
        ))}
      </nav>
      <Outlet />
    </div>
  );
}
