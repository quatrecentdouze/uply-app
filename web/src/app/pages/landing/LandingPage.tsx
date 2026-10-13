import { LandingHeader } from '@/app/pages/landing/LandingHeader';
import { LandingHero } from '@/app/pages/landing/LandingHero';
import { LandingShowcase } from '@/app/pages/landing/LandingShowcase';

export function LandingPage() {
  return (
    <div className="min-h-svh bg-page">
      <LandingHeader />
      <main id="main" className="mx-auto w-[min(1160px,calc(100%-32px))] pb-16 sm:w-[min(1160px,calc(100%-64px))]">
        <LandingHero />
        <LandingShowcase />
      </main>
    </div>
  );
}
