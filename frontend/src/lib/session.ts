import { get, writable } from 'svelte/store';
import { apiJson } from '$lib/api';

export type Session = {
  authenticated: boolean;
  id?: number;
  email?: string;
  enabled?: boolean;
  emailVerified?: boolean;
  nickname?: string | null;
  name?: string | null;
  surname?: string | null;
};

const anonymousSession: Session = { authenticated: false };

export const session = writable<Session>(anonymousSession);
export const sessionLoading = writable(false);

let sessionLoaded = false;
let sessionRequest: Promise<Session> | null = null;
let sessionRequestId = 0;

function requestSession({ force = false }: { force?: boolean } = {}): Promise<Session> {
  if (sessionRequest && !force) {
    return sessionRequest;
  }

  const requestId = ++sessionRequestId;
  sessionLoading.set(true);

  const request = apiJson<Session>('/api/session')
    .then((current) => {
      if (requestId === sessionRequestId) {
        session.set(current);
        sessionLoaded = true;
      }
      return current;
    })
    .catch((error) => {
      if (requestId === sessionRequestId) {
        session.set(anonymousSession);
        sessionLoaded = true;
      }
      throw error;
    })
    .finally(() => {
      if (requestId === sessionRequestId) {
        sessionRequest = null;
        sessionLoading.set(false);
      }
    });

  sessionRequest = request;

  return request;
}

export async function ensureSessionLoaded(): Promise<Session> {
  if (sessionLoaded) {
    return get(session);
  }

  return requestSession().catch(() => get(session));
}

export async function loadSession(): Promise<Session> {
  sessionLoaded = false;
  return requestSession({ force: true });
}

export async function logout(): Promise<void> {
  await apiJson<Session>('/api/auth/logout', { method: 'POST' });
  sessionRequestId += 1;
  sessionRequest = null;
  session.set(anonymousSession);
  sessionLoaded = true;
  sessionLoading.set(false);
}
