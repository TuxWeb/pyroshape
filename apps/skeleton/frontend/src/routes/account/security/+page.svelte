<script lang="ts">
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { ApiError } from '#lib/api.js';
  import { clearSession, ensureSessionLoaded } from '#lib/session.js';
  import {
    authErrorCode,
    beginSetup,
    confirmSetup,
    disableTotp,
    getTotpStatus,
    type Provisioning,
    type TotpStatus
  } from '#lib/totp.js';
  import AuthenticatorCode from '#lib/components/auth/AuthenticatorCode.svelte';
  import AuthenticatorSetup from '#lib/components/auth/AuthenticatorSetup.svelte';
  import SaveRecoveryCode from '#lib/components/auth/SaveRecoveryCode.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Card from '#lib/components/ui/Card.svelte';
  import ErrorMessage from '#lib/components/ui/ErrorMessage.svelte';
  import FormField from '#lib/components/ui/FormField.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import LoadingState from '#lib/components/ui/LoadingState.svelte';

  type SecurityAction =
    | { name: 'setup' | 'disable' | 'disabled' }
    | { name: 'confirm'; flow: Provisioning }
    | { name: 'save'; recoveryCode: string };

  let status = $state<TotpStatus>();
  let action = $state<SecurityAction>();
  let currentPassword = $state('');
  let code = $state('');
  let error = $state('');
  let loading = $state(true);
  let submitting = $state(false);

  onMount(() => {
    let active = true;

    async function load() {
      const current = await ensureSessionLoaded();
      if (!active) return;
      if (!current.authenticated) {
        await goto('/login');
        return;
      }

      try {
        const result = await getTotpStatus();
        if (active) status = result;
      } catch (caught) {
        if (active) await showError(caught);
      } finally {
        if (active) loading = false;
      }
    }

    void load();
    return () => { active = false; };
  });

  $effect(() => {
    if (action?.name !== 'confirm' || submitting) return;

    const timer = window.setTimeout(
      () => resetAction('This setup request has expired. Start setup again.'),
      Math.max(0, Date.parse(action.flow.expiresAt) - Date.now())
    );
    return () => window.clearTimeout(timer);
  });

  function resetAction(message = '') {
    action = undefined;
    currentPassword = '';
    code = '';
    error = message;
  }

  function selectAction(name: 'setup' | 'disable') {
    resetAction();
    action = { name };
  }

  async function returnToSignIn() {
    resetAction();
    await goto('/login');
  }

  async function showError(caught: unknown) {
    switch (authErrorCode(caught)) {
      case 'current_session_required':
        clearSession();
        await returnToSignIn();
        break;
      case 'flow_unavailable':
        resetAction('This setup request is no longer available. Start setup again.');
        break;
      default:
        error = caught instanceof ApiError ? caught.message : 'The security request failed. Please try again.';
    }
  }

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    if (submitting || !action) return;

    error = '';
    submitting = true;
    try {
      switch (action.name) {
        case 'setup': {
          const flow = await beginSetup(currentPassword, status?.enabled ? code : undefined);
          action = { name: 'confirm', flow };
          break;
        }
        case 'confirm': {
          const result = await confirmSetup(action.flow.flowId, code);
          status = undefined;
          clearSession();
          action = { name: 'save', recoveryCode: result.recoveryCode };
          break;
        }
        case 'disable':
          await disableTotp(currentPassword, code);
          status = undefined;
          clearSession();
          action = { name: 'disabled' };
          break;
      }
    } catch (caught) {
      await showError(caught);
    } finally {
      currentPassword = '';
      code = '';
      submitting = false;
    }
  }

  function formatDate(date: string | null): string {
    return date
      ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date))
      : '—';
  }
</script>

<svelte:window onpagehide={() => resetAction()} />

<svelte:head>
  <title>Account security | Pyroshape</title>
</svelte:head>

<div class="mx-auto grid max-w-md gap-6">
  <h1 class="text-2xl font-semibold">Account security</h1>

  {#if loading}
    <LoadingState label="Loading account security..." />
  {:else if action?.name === 'save'}
    <Card class="p-5">
      <SaveRecoveryCode code={action.recoveryCode} oncontinue={returnToSignIn} />
    </Card>
  {:else if action?.name === 'disabled'}
    <Card class="grid gap-4 p-5">
      <p>Authenticator sign-in is disabled. Sign in again to continue.</p>
      <Button type="button" onclick={returnToSignIn}>Continue to sign-in</Button>
    </Card>
  {:else if status}
    <Card class="grid gap-4 p-5">
      <h2 class="text-lg font-semibold">Authenticator app</h2>
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
        <dt class="text-muted-foreground">Status</dt>
        <dd>{status.enabled ? 'Enabled' : 'Disabled'}</dd>
        <dt class="text-muted-foreground">Last enabled</dt>
        <dd>{formatDate(status.enabledAt)}</dd>
        <dt class="text-muted-foreground">Last disabled</dt>
        <dd>{formatDate(status.disabledAt)}</dd>
      </dl>
      {#if !action}
        <ErrorMessage message={error} />
        {#if status.enabled}
          <div class="flex flex-wrap gap-2">
            <Button type="button" onclick={() => selectAction('setup')}>Replace authenticator</Button>
            <Button type="button" variant="secondary" onclick={() => selectAction('disable')}>Disable</Button>
          </div>
        {:else if status.enrollmentAvailable}
          <Button type="button" onclick={() => selectAction('setup')}>Set up authenticator</Button>
        {:else}
          <p class="text-sm text-muted-foreground">Authenticator setup is currently unavailable.</p>
        {/if}
      {/if}
    </Card>

    {#if action}
      <Card class="p-5">
        <form class="grid gap-4" onsubmit={submit}>
          {#if action.name === 'confirm'}
            <h2 class="text-lg font-semibold">Confirm your authenticator</h2>
            <AuthenticatorSetup provisioningUri={action.flow.provisioningUri} manualKey={action.flow.manualKey} />
            <AuthenticatorCode label="Code from the new authenticator" bind:value={code} />
          {:else}
            {#if action.name === 'disable'}
              <h2 class="text-lg font-semibold">Disable authenticator sign-in</h2>
              <p class="text-sm text-muted-foreground">This removes the authenticator requirement from your account and signs you out of all sessions.</p>
            {:else}
              <h2 class="text-lg font-semibold">{status.enabled ? 'Replace your authenticator' : 'Set up an authenticator'}</h2>
            {/if}
            <FormField label="Current password">
              <Input bind:value={currentPassword} type="password" autocomplete="current-password" required />
            </FormField>
            {#if status.enabled}
              <AuthenticatorCode label="Code from the current authenticator" bind:value={code} />
            {/if}
          {/if}

          <ErrorMessage message={error} />

          <div class="flex flex-wrap gap-2">
            <Button type="submit" variant={action.name === 'disable' ? 'danger' : 'primary'} disabled={submitting}>
              {#if submitting}Please wait...
              {:else if action.name === 'confirm'}Confirm authenticator
              {:else if action.name === 'disable'}Confirm disable
              {:else}Continue{/if}
            </Button>
            <Button type="button" variant="secondary" disabled={submitting} onclick={() => resetAction()}>Cancel</Button>
          </div>
        </form>
      </Card>
    {/if}
  {:else}
    <ErrorMessage message={error} />
  {/if}
</div>
