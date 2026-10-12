import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { guest, renderApp } from '@/app/testing/renderApp';

describe('App', () => {
  it('opens the landing page for a guest', async () => {
    const app = renderApp({ path: '/', server: guest() });

    expect(await screen.findByRole('heading', { level: 1, name: /Your websites and APIs/ })).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: 'Get started' })[0]).toHaveAttribute('href', '/register');
    expect(screen.getAllByRole('link', { name: 'Sign in' })[0]).toHaveAttribute('href', '/login');
    expect(app.router.state.location.pathname).toBe('/');
  });
});
