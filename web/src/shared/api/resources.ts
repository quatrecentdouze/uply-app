import type { components, paths } from '@/shared/api/schema';

type Schemas = components['schemas'];

type JsonOf<Operation> = Operation extends { responses: { 200: { content: { 'application/json': infer Body } } } } ? Body : never;

export type Monitor = Schemas['MonitorResource'];
export type MonitorState = Monitor['state'];
export type Incident = Schemas['IncidentResource'];
export type Overview = Schemas['OverviewResource'];
export type SeriesPoint = Schemas['SeriesPointResource'];
export type Me = Schemas['MeResource'];
export type Membership = Schemas['MembershipResource'];
export type Organization = Schemas['OrganizationResource'];
export type Role = Schemas['Role'];
export type PublicInvitation = Schemas['PublicInvitationResource'];

export type MonitorListResponse = JsonOf<paths['/v1/organizations/{organization}/monitors']['get']>;
export type MonitorResponse = JsonOf<paths['/v1/organizations/{organization}/monitors/{monitor}']['get']>;
export type IncidentListResponse = JsonOf<paths['/v1/organizations/{organization}/incidents']['get']>;
export type OverviewResponse = JsonOf<paths['/v1/organizations/{organization}/overview']['get']>;
