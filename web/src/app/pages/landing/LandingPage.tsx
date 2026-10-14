import { LandingClosing, LandingFooter } from '@/app/pages/landing/LandingClosing';
import { LandingFaq } from '@/app/pages/landing/LandingFaq';
import { LandingFeatures } from '@/app/pages/landing/LandingFeatures';
import { LandingHeader } from '@/app/pages/landing/LandingHeader';
import { LandingHero } from '@/app/pages/landing/LandingHero';
import { LandingShowcase } from '@/app/pages/landing/LandingShowcase';
import { LandingSteps } from '@/app/pages/landing/LandingSteps';
import { landingContainer } from '@/app/pages/landing/container';

export function LandingPage() {
  return (
    <div className="min-h-svh bg-page">
      <LandingHeader />
      <main id="main">
        <div className={landingContainer}>
          <LandingHero />
          <LandingShowcase />
          <LandingFeatures />
        </div>
        <LandingSteps />
        <div className={landingContainer}>
          <LandingFaq />
          <LandingClosing />
        </div>
      </main>
      <div className={landingContainer}>
        <LandingFooter />
      </div>
    </div>
  );
}
