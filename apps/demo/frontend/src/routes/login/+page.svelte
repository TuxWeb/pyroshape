<script lang="ts">
  import { goto } from '$app/navigation';
  import { ApiError, apiJson } from '#lib/api.ts';
  import { loadSession, type Session } from '#lib/session.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Card from '#lib/components/ui/Card.svelte';
  import ErrorMessage from '#lib/components/ui/ErrorMessage.svelte';
  import FormField from '#lib/components/ui/FormField.svelte';
  import Input from '#lib/components/ui/Input.svelte';

  type PendingLogin = { authenticated: false; next: 'totp_required'; flowId: string; expiresAt: string };
  type LoginResult = (Session & { authenticated: true }) | PendingLogin;

  let pending = $state<PendingLogin>();
  let email = $state('');
  let password = $state('');
  let code = $state('');
  let error = $state('');
  let submitting = $state(false);

  $effect(() => {
    if (!pending || submitting) return;

    const timer = window.setTimeout(
      () => startOver('This sign-in request has expired. Enter your password to start again.'),
      Math.max(0, Date.parse(pending.expiresAt) - Date.now())
    );
    return () => window.clearTimeout(timer);
  });

  function startOver(message = '') {
    pending = undefined;
    password = '';
    code = '';
    error = message;
  }

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    if (submitting) return;

    error = '';
    submitting = true;
    try {
      const result = await apiJson<LoginResult>(pending ? '/api/auth/login/totp' : '/api/auth/login', {
        method: 'POST',
        body: JSON.stringify(pending ? { flowId: pending.flowId, code } : { email, password })
      });
      password = '';

      if (result.authenticated === true) {
        startOver();
        await loadSession();
        await goto('/projects');
      } else if (!pending && result.next === 'totp_required') {
        pending = result;
      } else {
        error = 'Sign-in could not be completed.';
      }
    } catch (caught) {
      if (caught instanceof ApiError) {
        const payload = caught.payload as { error?: { code?: unknown } } | null;
        if (pending && payload?.error?.code === 'flow_unavailable') {
          startOver('This sign-in request is no longer available. Enter your password to start again.');
        } else {
          error = caught.message;
        }
      } else {
        error = 'Sign-in failed. Please try again.';
      }
    } finally {
      code = '';
      submitting = false;
    }
  }
</script>

<svelte:window onpagehide={() => startOver()} />

<div class="mx-auto grid max-w-md gap-6">
  <div class="grid gap-2">
    <h1 class="text-2xl font-semibold">Sign in</h1>
    <p class="text-sm text-muted-foreground">
      {pending ? 'Enter the current code from your authenticator app.' : 'Use the email and password for your Pyroshape account.'}
    </p>
  </div>

  <Card class="p-5">
    <form class="grid gap-4" onsubmit={submit}>
      {#if pending}
        <FormField label="Authenticator code">
          <Input bind:value={code} type="text" inputmode="numeric" autocomplete="one-time-code" pattern={"[0-9]{6}"} maxlength={6} required />
        </FormField>
      {:else}
        <FormField label="Email">
          <Input bind:value={email} type="email" autocomplete="email" required />
        </FormField>

        <FormField label="Password">
          <Input bind:value={password} type="password" autocomplete="current-password" required />
        </FormField>
      {/if}

      <ErrorMessage message={error} />

      <Button type="submit" class="w-full" disabled={submitting}>
        {submitting ? 'Signing in...' : 'Sign in'}
      </Button>
      {#if pending}
        <Button type="button" variant="light" disabled={submitting} onclick={() => startOver()}>Back to password</Button>
      {/if}
    </form>
  </Card>

  {#if !pending}
    <p class="text-center text-sm text-muted-foreground">
      Need an account?
      <a class="font-medium text-primary hover:underline" href="/register">Register</a>
    </p>
  {/if}
</div>
