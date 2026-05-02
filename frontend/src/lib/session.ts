import { writable } from 'svelte/store';
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

export async function loadSession(): Promise<Session> {
  sessionLoading.set(true);

  try {
    const current = await apiJson<Session>('/api/session');
    session.set(current);
    return current;
  } finally {
    sessionLoading.set(false);
  }
}

export async function logout(): Promise<void> {
  await apiJson<Session>('/api/auth/logout', { method: 'POST' });
  session.set(anonymousSession);
}
