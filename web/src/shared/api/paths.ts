import type { components, operations, paths } from '@/shared/api/schema';

type Schemas = components['schemas'];

interface JsonBody<T> {
  content: { 'application/json': T };
}

type IntegerInterval<Body> = Omit<Body, 'interval_seconds'> & { interval_seconds?: number };

type PasswordInBody<Operation extends { parameters: { query: { password: string } } }> = Omit<Operation, 'parameters' | 'requestBody'> & {
  parameters: Omit<Operation['parameters'], 'query'> & { query?: never };
  requestBody: JsonBody<{ password: string }>;
};

interface Overlay {
  '/v1/organizations/{organization}/monitors': Omit<paths['/v1/organizations/{organization}/monitors'], 'post'> & {
    post: Omit<operations['monitors.store'], 'requestBody'> & { requestBody: JsonBody<IntegerInterval<Schemas['StoreMonitorRequest']>> };
  };
  '/v1/organizations/{organization}/monitors/{monitor}': Omit<paths['/v1/organizations/{organization}/monitors/{monitor}'], 'patch'> & {
    patch: Omit<operations['monitors.update'], 'requestBody'> & { requestBody: JsonBody<IntegerInterval<Schemas['UpdateMonitorRequest']>> };
  };
  '/v1/me': Omit<paths['/v1/me'], 'delete'> & { delete: PasswordInBody<operations['me.destroy']> };
  '/v1/organizations/{organization}': Omit<paths['/v1/organizations/{organization}'], 'delete'> & {
    delete: PasswordInBody<operations['organizations.destroy']>;
  };
}

export type ApiPaths = Omit<paths, keyof Overlay> & Overlay;
