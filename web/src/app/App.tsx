import { QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from '@tanstack/react-router';
import { useState } from 'react';
import { I18nextProvider } from 'react-i18next';
import { type AppServices, createAppServices } from '@/app/services';
import { ApiClientContext } from '@/shared/api/apiClientContext';
import { TooltipProvider } from '@/shared/ui/tooltip';

export function AppProviders({ services }: { services: AppServices }) {
  return (
    <I18nextProvider i18n={services.i18n}>
      <QueryClientProvider client={services.queryClient}>
        <ApiClientContext value={services.apiClient}>
          <TooltipProvider>
            <RouterProvider router={services.router} />
          </TooltipProvider>
        </ApiClientContext>
      </QueryClientProvider>
    </I18nextProvider>
  );
}

export function App() {
  const [services] = useState(() => createAppServices({ origin: window.location.origin }));
  return <AppProviders services={services} />;
}
