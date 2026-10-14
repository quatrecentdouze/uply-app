import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { TopBar } from '@/app/shell/TopBar';
import { meFixture } from '@/shared/testing/fixtures';
import { renderPage } from '@/shared/testing/renderPage';

function renderTopBar(livePaused: boolean) {
  return renderPage(() => <TopBar title="Dashboard" me={meFixture()} hasOpenIncident={false} livePaused={livePaused} onMenu={() => undefined} />);
}

describe('TopBar', () => {
  it('says live updates are paused while the live connection is down', async () => {
    renderTopBar(true);

    const status = await screen.findByRole('status');
    expect(status).toHaveTextContent('Live updates paused');
    expect(status).toHaveAttribute('title', 'The live connection is down. Pages still load their data when you open them.');
  });

  it('says nothing while the live connection works', async () => {
    renderTopBar(false);

    await screen.findByRole('heading', { name: 'Dashboard' });
    expect(screen.queryByRole('status')).toBeNull();
  });
});
