import type { components, paths } from '@/shared/api/schema';

interface NoParameters {
  query?: never;
  header?: never;
  path?: never;
  cookie?: never;
}

interface JsonBody<T> {
  content: { 'application/json': T };
}

interface JsonResponse<T> {
  headers: Record<string, unknown>;
  content: { 'application/json': T };
}

type ValidationResponse = components['responses']['ValidationException'];

export interface RegisterBody {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface ResetPasswordBody {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface ChannelAuthBody {
  socket_id: string;
  channel_name: string;
}

export interface ChannelAuthorization {
  auth: string;
  channel_data?: string;
}

interface Overlay {
  '/v1/auth/register': Omit<paths['/v1/auth/register'], 'post'> & {
    post: {
      parameters: NoParameters;
      requestBody: JsonBody<RegisterBody>;
      responses: { 201: JsonResponse<unknown>; 422: ValidationResponse };
    };
  };
  '/v1/auth/reset-password': Omit<paths['/v1/auth/reset-password'], 'post'> & {
    post: {
      parameters: NoParameters;
      requestBody: JsonBody<ResetPasswordBody>;
      responses: { 200: JsonResponse<unknown>; 422: ValidationResponse };
    };
  };
  '/broadcasting/auth': Omit<paths['/broadcasting/auth'], 'post'> & {
    post: {
      parameters: NoParameters;
      requestBody: JsonBody<ChannelAuthBody>;
      responses: { 200: JsonResponse<ChannelAuthorization> };
    };
  };
}

export type ApiPaths = Omit<paths, keyof Overlay> & Overlay;

export type Schemas = components['schemas'];
