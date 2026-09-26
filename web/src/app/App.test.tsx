import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { App } from '@/app/App';

describe('App', () => {
  it('renders the product name from the text files', () => {
    render(<App />);

    expect(screen.getByRole('heading', { name: 'Uply' })).toBeInTheDocument();
  });
});
