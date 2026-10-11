import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { InvitationViewer } from '@/features/organizations/model/organizations';
import { InvitationPage } from '@/features/organizations/pages/InvitationPage';
import { type FakeFetch, fakeFetch } from '@/shared/testing/fakeFetch';
import { renderPage } from '@/shared/testing/renderPage';

const token = 'a'.repeat(40);
const invitation = {
  organization: { name: 'Acme' },
  role: 'member',
  invited_by: { name: 'Ada' },
  email: 'invitee@example.test',
  expires_at: '2026-11-05T09:00:00Z',
};

function server(status = 200): FakeFetch {
  return fakeFetch([
    { method: 'GET', path: '/sanctum/csrf-cookie', status: 204 },
    { method: 'GET', path: `/api/v1/invitations/${token}`, status, body: status === 200 ? { data: invitation } : { message: 'x', code: 'invitation_expired' } },
  ]);
}

function open(viewer: InvitationViewer, backend = server(), onSwitchAccount = vi.fn()) {
  return renderPage(() => <InvitationPage token={token} viewer={viewer} onSwitchAccount={onSwitchAccount} />, { server: backend, path: `/invitations/${token}` });
}

describe('InvitationPage', () => {
  it('describes the invitation and asks a guest to sign in or sign up', async () => {
    open({ kind: 'guest' });

    expect(await screen.findByRole('heading', { name: 'Join Acme' })).toBeInTheDocument();
    expect(screen.getByText('Ada invited invitee@example.test to join as Member.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sign in to accept' })).toHaveAttribute('href', `/login?redirect=%2Finvitations%2F${token}`);
    expect(screen.getByRole('link', { name: 'Create an account' })).toHaveAttribute('href', `/register?redirect=%2Finvitations%2F${token}`);
  });

  it('accepts for the invited user and opens the new organization', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: `/api/v1/invitations/${token}/accept`, status: 201, body: { data: { id: 'org-acme', name: 'Acme', slug: 'acme', role: 'member', created_at: null } } });
    const page = open({ kind: 'verified', email: 'Invitee@Example.test' }, backend);

    await page.user.click(await screen.findByRole('button', { name: 'Accept invitation' }));

    await waitFor(() => {
      expect(page.router.state.location.pathname).toBe('/dashboard');
    });
    expect(window.localStorage.getItem('uply.organization')).toBe('org-acme');
  });

  it('shows why an accept was refused', async () => {
    const backend = server();
    backend.respond({ method: 'POST', path: `/api/v1/invitations/${token}/accept`, status: 409, body: { message: 'x', code: 'already_member' } });
    const page = open({ kind: 'verified', email: 'invitee@example.test' }, backend);

    await page.user.click(await screen.findByRole('button', { name: 'Accept invitation' }));

    expect(await screen.findByText('You are already a member of this organization.')).toBeInTheDocument();
  });

  it('offers to switch account for another email', async () => {
    const onSwitchAccount = vi.fn();
    const page = open({ kind: 'verified', email: 'other@example.test' }, server(), onSwitchAccount);

    expect(await screen.findByText('You are signed in as other@example.test, not with the invited email.')).toBeInTheDocument();
    await page.user.click(screen.getByRole('button', { name: 'Use another account' }));

    expect(onSwitchAccount).toHaveBeenCalledOnce();
  });

  it('asks the invited user to verify the email first', async () => {
    open({ kind: 'unverified', email: 'invitee@example.test' });

    expect(await screen.findByRole('link', { name: 'Verify my email' })).toHaveAttribute('href', '/verify-email');
  });

  it('says when the invitation has expired', async () => {
    open({ kind: 'guest' }, server(410));

    expect(await screen.findByText('This invitation has expired. Ask for a new one.')).toBeInTheDocument();
  });
});
