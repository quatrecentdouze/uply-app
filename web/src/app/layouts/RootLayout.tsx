import { Outlet } from '@tanstack/react-router';
import { DocumentTitle } from '@/app/shell/DocumentTitle';

export function RootLayout() {
  return (
    <>
      <DocumentTitle />
      <Outlet />
    </>
  );
}
