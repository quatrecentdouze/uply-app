import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { guest, renderApp } from '@/app/testing/renderApp';

describe('App', () => {
  it('opens the sign in screen for a guest', async () => {
    const app = renderApp({ path: '/', server: guest() });

    expect(await screen.findByRole('heading', { name: 'Sign in to Uply' })).toBeInTheDocument();
    expect(app.router.state.location.pathname).toBe('/login');
  });
});
