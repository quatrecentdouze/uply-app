import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { RoutePendingPage } from '@/app/pages/RoutePendingPage';
import { renderPage } from '@/shared/testing/renderPage';

describe('RoutePendingPage', () => {
  it('tells a screen reader that the page is loading', async () => {
    renderPage(() => <RoutePendingPage />);

    expect(await screen.findByRole('status', { name: 'Loading' })).toBeInTheDocument();
  });
});
