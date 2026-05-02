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

function requestSession(): Promise<Session> {
  if (sessionRequest) {
    return sessionRequest;
  }

  sessionLoading.set(true);

  sessionRequest = apiJson<Session>('/api/session')
    .then((current) => {
      session.set(current);
      sessionLoaded = true;
      return current;
    })
    .catch((error) => {
      session.set(anonymousSession);
      sessionLoaded = true;
      throw error;
    })
    .finally(() => {
      sessionRequest = null;
      sessionLoading.set(false);
    });

  return sessionRequest;
}

export async function ensureSessionLoaded(): Promise<Session> {
  if (sessionLoaded) {
    return get(session);
  }

  return requestSession().catch(() => get(session));
}

export async function loadSession(): Promise<Session> {
  sessionLoaded = false;
  return requestSession();
}

export async function logout(): Promise<void> {
  await apiJson<Session>('/api/auth/logout', { method: 'POST' });
  session.set(anonymousSession);
  sessionLoaded = true;
}
