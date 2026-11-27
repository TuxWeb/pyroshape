import { ApiError, apiJson } from '#lib/api.js';
import type { Session } from '#lib/session.js';

export type AuthenticatedSession = Session & { authenticated: true };

export type AuthFlow = { flowId: string; expiresAt: string };
export type PendingLogin = AuthFlow & { authenticated: false; next: 'totp_required' };
export type Provisioning = AuthFlow & { provisioningUri: string; manualKey: string };

export type TotpStatus = {
  enrollmentAvailable: boolean;
  enabled: boolean;
  enabledAt: string | null;
  disabledAt: string | null;
};

type FactorChange = TotpStatus & { reauthenticationRequired: true };
type FactorChangeWithRecoveryCode = FactorChange & { recoveryCode: string };

function post<T>(action: string, body: Record<string, string>): Promise<T> {
  return apiJson<T>(`/api/auth/${action}`, { method: 'POST', body: JSON.stringify(body) });
}

export const signIn = (email: string, password: string) =>
  post<AuthenticatedSession | PendingLogin>('login', { email, password });

export const verifySignIn = (flowId: string, code: string) =>
  post<AuthenticatedSession>('login/totp', { flowId, code });

export const getTotpStatus = () => apiJson<TotpStatus>('/api/auth/totp');

export const beginSetup = (currentPassword: string, code?: string) =>
  post<Provisioning>('totp/setup', code === undefined ? { currentPassword } : { currentPassword, code });

export const confirmSetup = (flowId: string, code: string) =>
  post<FactorChangeWithRecoveryCode>('totp/setup/confirm', { flowId, code });

export const disableTotp = (currentPassword: string, code: string) =>
  post<FactorChange>('totp/disable', { currentPassword, code });

export const beginRecovery = (flowId: string, recoveryCode: string) =>
  post<Provisioning>('totp/recovery', { flowId, recoveryCode });

export const confirmRecovery = (flowId: string, code: string) =>
  post<FactorChangeWithRecoveryCode>('totp/recovery/confirm', { flowId, code });

export function authErrorCode(error: unknown): string | undefined {
  if (!(error instanceof ApiError)) return;
  const payload = error.payload as { error?: { code?: unknown } } | null;
  const code = payload?.error?.code;
  return typeof code === 'string' ? code : undefined;
}
