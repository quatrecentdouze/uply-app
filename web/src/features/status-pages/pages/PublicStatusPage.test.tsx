import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PublicStatusPage } from '@/features/status-pages/pages/PublicStatusPage';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { metaFixture, publicStatusPageFixture } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

const path = '/api/v1/public/status-pages/demo-services';

function server(status = 200, body: unknown = { data: publicStatusPageFixture() }) {
  return fakeFetch([
    { method: 'GET', path, status, body },
    { method: 'GET', path: '/api/v1/meta', body: { data: metaFixture() } },
  ]);
}

describe('PublicStatusPage', () => {
  it('shows the banner, a card per monitor with its bars and the incident history', async () => {
    renderPage(() => <PublicStatusPage slug="demo-services" />, { server: server(), path: '/status/demo-services' });

    expect(await screen.findByRole('heading', { level: 1, name: 'Demo services' })).toBeInTheDocument();
    expect(screen.getByText('Live state of the demo services.')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Partial outage');

    const cards = screen.getAllByRole('listitem').filter((item) => within(item).queryByRole('img') !== null);
    expect(cards).toHaveLength(2);
    const website = within(cards[0] ?? document.body);
    expect(website.getByRole('heading', { name: 'Website' })).toBeInTheDocument();
    expect(website.getByText('Operational')).toBeInTheDocument();
    expect(website.getByText('99.95% uptime')).toBeInTheDocument();
    expect(website.getByRole('img', { name: 'Uptime of Website over the last 90 days: 99.95%' })).toBeInTheDocument();

    expect(await screen.findByRole('heading', { name: 'Incidents of the last 14 days' })).toBeInTheDocument();
    expect(screen.getByText('Ongoing')).toBeInTheDocument();
    expect(screen.getByText('Resolved after 4 min 12 s')).toBeInTheDocument();
  });

  it('draws 90 hidden bars from oldest to newest, and keeps only the last 30 on a narrow screen', async () => {
    renderPage(() => <PublicStatusPage slug="demo-services" />, { server: server(), path: '/status/demo-services' });

    const strip = await screen.findByRole('img', { name: /Uptime of Public API/ });
    const bars = Array.from(strip.children);
    expect(bars).toHaveLength(90);
    expect(bars.every((bar) => bar.getAttribute('aria-hidden') === 'true')).toBe(true);
    expect(bars.filter((bar) => bar.className.includes('max-sm:hidden'))).toHaveLength(60);
    expect(bars.slice(0, 10).every((bar) => bar.getAttribute('data-tone') === 'neutral')).toBe(true);
    expect(bars.at(-1)?.getAttribute('data-tone')).toBe('alert');
  });

  it('shows the day and its uptime when a bar is pointed at', async () => {
    renderPage(() => <PublicStatusPage slug="demo-services" />, { server: server(), path: '/status/demo-services' });

    const strip = await screen.findByRole('img', { name: /Uptime of Public API/ });
    fireEvent.pointerMove(strip.children[89] ?? strip);
    expect(screen.getByTestId('bar-tooltip')).toHaveTextContent('Oct 29: 62.5% uptime');

    fireEvent.pointerDown(strip.children[0] ?? strip);
    expect(screen.getByTestId('bar-tooltip')).toHaveTextContent('Aug 1: no data');

    fireEvent.pointerLeave(strip);
    expect(screen.queryByTestId('bar-tooltip')).toBeNull();
  });

  it('shows its own not found page for an unknown or unpublished slug', async () => {
    renderPage(() => <PublicStatusPage slug="demo-services" />, { server: server(404, { message: 'Not found.' }), path: '/status/demo-services' });

    expect(await screen.findByRole('heading', { name: 'Status page not found' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Go to Uply' })).toHaveAttribute('href', '/');
  });

  it('offers a retry when the page cannot load', async () => {
    const backend = server(500, { message: 'Server Error' });
    const page = renderPage(() => <PublicStatusPage slug="demo-services" />, { server: backend, path: '/status/demo-services' });

    await page.user.click(await screen.findByRole('button', { name: 'Retry' }));
    backend.respond({ method: 'GET', path, body: { data: publicStatusPageFixture({ overall_state: 'operational' }) } });
    await page.user.click(await screen.findByRole('button', { name: 'Retry' }));

    expect(await screen.findByRole('status')).toHaveTextContent('All systems operational');
  });

  it('never asks for the session and names the tab after the page', async () => {
    const backend = server();
    renderPage(() => <PublicStatusPage slug="demo-services" />, { server: backend, path: '/status/demo-services' });

    await screen.findByRole('heading', { level: 1, name: 'Demo services' });
    await waitFor(() => {
      expect(document.title).toBe('Demo services · Uply');
    });
    expect(backend.requestsTo('GET', '/api/v1/me')).toHaveLength(0);
  });
});
