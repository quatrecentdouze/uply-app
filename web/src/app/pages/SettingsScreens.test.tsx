import { screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import type { Me, Member, Role } from '@/shared/api/resources';
import { type FakeFetch, fakeFetch } from '@/shared/testing/fakeFetch';
import { listOf, meFixture, organizationId } from '@/shared/testing/fixtures';

const base = `/api/v1/organizations/${organizationId}`;

function meAs(role: Role, overrides: Partial<Me> = {}): Me {
  return meFixture({ memberships: [{ id: 'm-self', role, organization: { id: organizationId, name: 'Demo', slug: 'demo' }, joined_at: null }], ...overrides });
}

function member(id: string, role: Role, name: string, userId = `user-${id}`): Member {
  return { id, role, user: { id: userId, name, email: `${name.split(' ')[0]?.toLowerCase() ?? 'x'}@example.test` }, joined_at: '2026-10-28T08:00:00Z' };
}

function open(path: string, me: Me = meAs('owner'), setup: (server: FakeFetch) => void = () => undefined) {
  const server = withWorkspace(signedIn(fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]), me));
  server.respond({
    method: 'GET',
    path: `${base}/members`,
    body: listOf([member('m-self', me.memberships[0]?.role ?? 'owner', me.name, me.id), member('m-viewer', 'viewer', 'Demo Viewer'), member('m-owner2', 'owner', 'Second Owner')]),
  });
  server.respond({ method: 'GET', path: `${base}/invitations`, body: listOf([{ id: 'inv-1', email: 'pending@example.test', role: 'member', invited_by: { id: me.id, name: me.name }, expires_at: '2026-11-05T08:00:00Z', created_at: null }]) });
  setup(server);
  return renderApp({ path, server });
}

describe('settings', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('opens the organization tab from the sidebar entry', async () => {
    const app = open('/settings');

    await waitFor(() => {
      expect(app.router.state.location.pathname).toBe('/settings/organization');
    });
    expect(await screen.findByRole('link', { name: 'Members' })).toHaveAttribute('href', '/settings/members');
  });

  describe('organization', () => {
    it('renames the organization', async () => {
      const app = open('/settings/organization');
      app.server.respond({ method: 'PATCH', path: base, body: { data: { id: organizationId, name: 'Acme', slug: 'demo', role: 'owner', created_at: null } } });

      const name = await screen.findByLabelText('Organization name');
      await waitFor(() => {
        expect(name).toBeEnabled();
      });
      await app.user.clear(name);
      await app.user.type(name, 'Acme');
      await app.user.click(screen.getByRole('button', { name: 'Save name' }));

      await waitFor(() => {
        expect(app.server.requestsTo('PATCH', base)[0]?.body).toEqual({ name: 'Acme' });
      });
    });

    it('deletes the organization with the password in the body and shows a wrong password', async () => {
      const app = open('/settings/organization');
      app.server.respond({ method: 'DELETE', path: base, status: 422, body: { message: 'Invalid.', errors: { password: ['The password is incorrect.'] } } });

      await app.user.click(await screen.findByRole('button', { name: 'Delete organization' }));
      const dialog = within(await screen.findByRole('alertdialog', { name: 'Delete Demo?' }));
      expect(dialog.getByRole('button', { name: 'Delete organization' })).toBeDisabled();
      await app.user.type(dialog.getByLabelText('Password'), 'not it');
      await app.user.click(dialog.getByRole('button', { name: 'Delete organization' }));

      expect(await dialog.findByText('The password is incorrect.')).toBeInTheDocument();
      const request = app.server.requestsTo('DELETE', base)[0];
      expect(request?.body).toEqual({ password: 'not it' });
      expect(request?.search).toBe('');
    });

    it('hides the rename and the delete from a viewer and the delete from an admin', async () => {
      open('/settings/organization', meAs('viewer'));
      expect(await screen.findByLabelText('Organization name')).toBeDisabled();
      expect(screen.queryByRole('button', { name: 'Delete organization' })).toBeNull();
    });

    it('lets an admin rename but not delete', async () => {
      open('/settings/organization', meAs('admin'));

      const name = await screen.findByLabelText('Organization name');
      await waitFor(() => {
        expect(name).toBeEnabled();
      });
      expect(screen.queryByRole('button', { name: 'Delete organization' })).toBeNull();
    });
  });

  describe('members', () => {
    it('changes a role', async () => {
      const app = open('/settings/members');
      app.server.respond({ method: 'PATCH', path: `${base}/members/m-viewer`, body: { data: member('m-viewer', 'member', 'Demo Viewer') } });

      await app.user.selectOptions(await screen.findByRole('combobox', { name: 'Role of Demo Viewer' }), 'Member');

      await waitFor(() => {
        expect(app.server.requestsTo('PATCH', `${base}/members/m-viewer`)[0]?.body).toEqual({ role: 'member' });
      });
    });

    it('says when the organization would lose its last owner', async () => {
      const app = open('/settings/members');
      app.server.respond({ method: 'PATCH', path: `${base}/members/m-owner2`, status: 409, body: { message: 'Last owner.', code: 'last_owner' } });

      await app.user.selectOptions(await screen.findByRole('combobox', { name: 'Role of Second Owner' }), 'Admin');

      expect(await screen.findByText('An organization must keep at least one owner.')).toBeInTheDocument();
    });

    it('says when an admin touches an owner role', async () => {
      const app = open('/settings/members', meAs('admin'));
      app.server.respond({ method: 'PATCH', path: `${base}/members/m-viewer`, status: 403, body: { message: 'Not allowed.', code: 'role_not_allowed' } });

      expect(screen.queryByRole('combobox', { name: 'Role of Second Owner' })).toBeNull();
      const select = await screen.findByRole('combobox', { name: 'Role of Demo Viewer' });
      expect(within(select).queryByRole('option', { name: 'Owner' })).toBeNull();
      await app.user.selectOptions(select, 'Admin');

      expect(await screen.findByText('Your role cannot grant or change this role.')).toBeInTheDocument();
    });

    it('removes a member after the confirmation', async () => {
      const app = open('/settings/members');
      app.server.respond({ method: 'DELETE', path: `${base}/members/m-viewer`, status: 204 });

      const row = (await screen.findByText('Demo Viewer')).closest('tr');
      if (row === null) {
        throw new Error('row missing');
      }
      await app.user.click(within(row).getByRole('button', { name: 'Remove' }));
      await app.user.click(within(await screen.findByRole('alertdialog', { name: 'Remove Demo Viewer?' })).getByRole('button', { name: 'Remove' }));

      await waitFor(() => {
        expect(app.server.requestsTo('DELETE', `${base}/members/m-viewer`)).toHaveLength(1);
      });
    });

    it('leaves the organization and goes home', async () => {
      const app = open('/settings/members', meAs('owner'));
      app.server.respond({ method: 'DELETE', path: `${base}/members/m-self`, status: 204 });

      await app.user.click(await screen.findByRole('button', { name: 'Leave' }));
      await app.user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Leave' }));

      await waitFor(() => {
        expect(app.server.requestsTo('DELETE', `${base}/members/m-self`)).toHaveLength(1);
      });
      await waitFor(() => {
        expect(app.router.state.location.pathname).toBe('/');
      });
    });

    it('invites a member', async () => {
      const app = open('/settings/members');
      app.server.respond({ method: 'POST', path: `${base}/invitations`, status: 201, body: { data: { id: 'inv-2', email: 'new@example.test', role: 'viewer', invited_by: null, expires_at: null, created_at: null } } });

      await app.user.type(await screen.findByLabelText('Email to invite'), 'new@example.test');
      await app.user.selectOptions(screen.getByLabelText('Role'), 'Viewer');
      await app.user.click(screen.getByRole('button', { name: 'Send invitation' }));

      expect(await screen.findByText('Invitation sent to new@example.test.')).toBeInTheDocument();
      expect(app.server.requestsTo('POST', `${base}/invitations`)[0]?.body).toEqual({ email: 'new@example.test', role: 'viewer' });
    });

    it.each([
      ['invitation_pending', 'An invitation is already pending for this email.'],
      ['already_member', 'You are already a member of this organization.'],
      ['invitation_limit_reached', 'This organization has too many pending invitations.'],
    ])('shows the %s refusal of an invitation', async (code, text) => {
      const app = open('/settings/members');
      app.server.respond({ method: 'POST', path: `${base}/invitations`, status: 409, body: { message: 'Refused.', code } });

      await app.user.type(await screen.findByLabelText('Email to invite'), 'new@example.test');
      await app.user.click(screen.getByRole('button', { name: 'Send invitation' }));

      expect(await screen.findByText(text)).toBeInTheDocument();
    });

    it('revokes an invitation after the confirmation', async () => {
      const app = open('/settings/members');
      app.server.respond({ method: 'DELETE', path: `${base}/invitations/inv-1`, status: 204 });

      await app.user.click(await screen.findByRole('button', { name: 'Revoke' }));
      await app.user.click(within(await screen.findByRole('alertdialog', { name: 'Revoke the invitation of pending@example.test?' })).getByRole('button', { name: 'Revoke' }));

      await waitFor(() => {
        expect(app.server.requestsTo('DELETE', `${base}/invitations/inv-1`)).toHaveLength(1);
      });
    });

    it('shows a viewer the members without any action and no invitation', async () => {
      open('/settings/members', meAs('viewer'));

      expect(await screen.findByText('Demo Viewer')).toBeInTheDocument();
      expect(screen.queryByRole('combobox')).toBeNull();
      expect(screen.queryByRole('button', { name: 'Remove' })).toBeNull();
      expect(screen.queryByRole('heading', { name: 'Invitations' })).toBeNull();
    });
  });

  describe('profile', () => {
    it('saves the name', async () => {
      const app = open('/settings/profile');
      app.server.respond({ method: 'PATCH', path: '/api/v1/me', body: { data: meAs('owner', { name: 'Ada' }) } });

      const name = await screen.findByLabelText('Name');
      await app.user.clear(name);
      await app.user.type(name, 'Ada');
      await app.user.click(screen.getByRole('button', { name: 'Save profile' }));

      expect(await screen.findByText('Your profile is saved.')).toBeInTheDocument();
      expect(app.server.requestsTo('PATCH', '/api/v1/me')[0]?.body).toEqual({ name: 'Ada' });
    });

    it('asks for the password to change the email', async () => {
      const app = open('/settings/profile');
      app.server.respond({ method: 'PATCH', path: '/api/v1/me', body: { data: meAs('owner', { email: 'ada@uply.test', email_verified_at: null }) } });

      const email = await screen.findByLabelText('Email');
      await app.user.clear(email);
      await app.user.type(email, 'ada@uply.test');
      const profile = within(screen.getByRole('region', { name: 'Profile' }));
      await app.user.type(await profile.findByLabelText('Current password'), 'password');
      await app.user.click(profile.getByRole('button', { name: 'Save profile' }));

      expect(await screen.findByText('Your profile is saved. Open the link we sent to the new address to verify it.')).toBeInTheDocument();
      expect(app.server.requestsTo('PATCH', '/api/v1/me')[0]?.body).toEqual({ email: 'ada@uply.test', current_password: 'password' });
    });

    it('changes the password and shows a wrong current password', async () => {
      const app = open('/settings/profile');
      app.server.respond({ method: 'PUT', path: '/api/v1/me/password', status: 422, body: { message: 'Invalid.', errors: { current_password: ['The password is incorrect.'] } } });

      const card = within(await screen.findByRole('region', { name: 'Password' }));
      await app.user.type(card.getByLabelText('Current password'), 'wrong');
      await app.user.type(card.getByLabelText('New password'), 'a much longer password');
      await app.user.type(card.getByLabelText('Confirm the new password'), 'a much longer password');
      await app.user.click(card.getByRole('button', { name: 'Change password' }));

      expect(await card.findByText('The password is incorrect.')).toBeInTheDocument();
      expect(app.server.requestsTo('PUT', '/api/v1/me/password')[0]?.body).toEqual({
        current_password: 'wrong',
        password: 'a much longer password',
        password_confirmation: 'a much longer password',
      });
    });
  });

  describe('security', () => {
    function twoFactorServer(server: FakeFetch) {
      server.respond({ method: 'GET', path: '/api/v1/auth/user/confirmed-password-status', body: { confirmed: false } });
      server.respond({ method: 'POST', path: '/api/v1/auth/user/confirm-password', status: 201, body: {} });
      server.respond({ method: 'POST', path: '/api/v1/auth/user/two-factor-authentication', body: {} });
      server.respond({ method: 'GET', path: '/api/v1/auth/user/two-factor-qr-code', body: { svg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>', url: 'otpauth://totp/Uply:demo' } });
      server.respond({ method: 'POST', path: '/api/v1/auth/user/confirmed-two-factor-authentication', body: {} });
      server.respond({ method: 'GET', path: '/api/v1/auth/user/two-factor-recovery-codes', body: ['code-one', 'code-two'] });
    }

    it('turns on two factor after the password, the QR code and the confirmation code', async () => {
      const app = open('/settings/security', meAs('owner'), twoFactorServer);

      await app.user.click(await screen.findByRole('button', { name: 'Turn on' }));
      await app.user.type(await screen.findByLabelText('Password'), 'password');
      await app.user.click(screen.getByRole('button', { name: 'Confirm password' }));

      expect(await screen.findByRole('img', { name: 'QR code to scan with an authenticator app' })).toHaveAttribute('src', expect.stringContaining('data:image/svg+xml'));
      await app.user.type(screen.getByLabelText('Code from the app'), '12345');
      await app.user.click(screen.getByRole('button', { name: 'Turn on' }));
      expect(await screen.findByText('Enter the 6 digits of the code.')).toBeInTheDocument();
      await app.user.type(screen.getByLabelText('Code from the app'), '6');
      await app.user.click(screen.getByRole('button', { name: 'Turn on' }));

      const codes = within(await screen.findByRole('list', { name: 'Recovery codes' }));
      expect(codes.getByText('code-one')).toBeInTheDocument();
      expect(app.server.requestsTo('POST', '/api/v1/auth/user/confirm-password')[0]?.body).toEqual({ password: 'password' });
      expect(app.server.requestsTo('POST', '/api/v1/auth/user/confirmed-two-factor-authentication')[0]?.body).toEqual({ code: '123456' });
    });

    it('shows a wrong confirmation code', async () => {
      const app = open('/settings/security', meAs('owner'), (server) => {
        twoFactorServer(server);
        server.respond({ method: 'GET', path: '/api/v1/auth/user/confirmed-password-status', body: { confirmed: true } });
        server.respond({ method: 'POST', path: '/api/v1/auth/user/confirmed-two-factor-authentication', status: 422, body: { message: 'Invalid.', errors: { code: ['The provided two factor authentication code was invalid.'] } } });
      });

      await app.user.click(await screen.findByRole('button', { name: 'Turn on' }));
      await app.user.type(await screen.findByLabelText('Code from the app'), '000000');
      await app.user.click(screen.getByRole('button', { name: 'Turn on' }));

      expect(await screen.findByText('The provided two factor authentication code was invalid.')).toBeInTheDocument();
    });

    it('turns off two factor after the confirmation', async () => {
      const app = open('/settings/security', meAs('owner', { two_factor_enabled: true }), (server) => {
        server.respond({ method: 'GET', path: '/api/v1/auth/user/confirmed-password-status', body: { confirmed: true } });
        server.respond({ method: 'DELETE', path: '/api/v1/auth/user/two-factor-authentication', body: {} });
      });

      await app.user.click(await screen.findByRole('button', { name: 'Turn off' }));
      await app.user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Turn off' }));

      await waitFor(() => {
        expect(app.server.requestsTo('DELETE', '/api/v1/auth/user/two-factor-authentication')).toHaveLength(1);
      });
    });

    it('asks for the password again when the API wants it', async () => {
      const app = open('/settings/security', meAs('owner', { two_factor_enabled: true }), (server) => {
        server.respond({ method: 'GET', path: '/api/v1/auth/user/confirmed-password-status', body: { confirmed: true } });
        server.respond({ method: 'POST', path: '/api/v1/auth/user/two-factor-recovery-codes', status: 423, body: { message: 'Password confirmation required.' } });
      });

      await app.user.click(await screen.findByRole('button', { name: 'New recovery codes' }));

      expect(await screen.findByText('Confirm your password first.')).toBeInTheDocument();
    });

    it('deletes the account with the password and goes to sign in', async () => {
      const app = open('/settings/security', meAs('owner'), (server) => {
        server.respond({ method: 'DELETE', path: '/api/v1/me', status: 204 });
      });

      await app.user.click(await screen.findByRole('button', { name: 'Delete account' }));
      const dialog = within(await screen.findByRole('alertdialog', { name: 'Delete your account?' }));
      await app.user.type(dialog.getByLabelText('Password'), 'password');
      app.server.respond({ method: 'GET', path: '/api/v1/me', status: 401, body: { message: 'Unauthenticated.' } });
      await app.user.click(dialog.getByRole('button', { name: 'Delete account' }));

      await waitFor(() => {
        expect(app.router.state.location.pathname).toBe('/login');
      });
      expect(app.server.requestsTo('DELETE', '/api/v1/me')[0]?.body).toEqual({ password: 'password' });
    });

    it('refuses to delete the only owner of a shared organization', async () => {
      const app = open('/settings/security', meAs('owner'), (server) => {
        server.respond({ method: 'DELETE', path: '/api/v1/me', status: 409, body: { message: 'Sole owner.', code: 'sole_owner' } });
      });

      await app.user.click(await screen.findByRole('button', { name: 'Delete account' }));
      const dialog = within(await screen.findByRole('alertdialog'));
      await app.user.type(dialog.getByLabelText('Password'), 'password');
      await app.user.click(dialog.getByRole('button', { name: 'Delete account' }));

      expect(await dialog.findByText('Transfer or delete the organizations you own alone before deleting your account.')).toBeInTheDocument();
    });
  });
});
