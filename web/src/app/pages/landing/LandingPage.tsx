import { LandingHeader } from '@/app/pages/landing/LandingHeader';
import { LandingFeatures } from '@/app/pages/landing/LandingFeatures';
import { LandingHero } from '@/app/pages/landing/LandingHero';
import { LandingShowcase } from '@/app/pages/landing/LandingShowcase';

export function LandingPage() {
  return (
    <div className="min-h-svh bg-page">
      <LandingHeader />
      <main id="main" className="mx-auto w-[min(1160px,calc(100%-32px))] pb-16 sm:w-[min(1160px,calc(100%-64px))]">
        <LandingHero />
        <LandingShowcase />
        <LandingFeatures />
      </main>
    </div>
  );
}
