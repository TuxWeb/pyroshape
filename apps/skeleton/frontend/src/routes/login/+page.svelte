<script lang="ts">
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { ApiError } from '#lib/api.js';
  import { clearSession, ensureSessionLoaded, loadSession } from '#lib/session.js';
  import {
    authErrorCode,
    beginRecovery,
    confirmRecovery,
    signIn,
    verifySignIn,
    type AuthFlow,
    type Provisioning
  } from '#lib/totp.js';
  import AuthenticatorCode from '#lib/components/auth/AuthenticatorCode.svelte';
  import AuthenticatorSetup from '#lib/components/auth/AuthenticatorSetup.svelte';
  import SaveRecoveryCode from '#lib/components/auth/SaveRecoveryCode.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Card from '#lib/components/ui/Card.svelte';
  import ErrorMessage from '#lib/components/ui/ErrorMessage.svelte';
  import FormField from '#lib/components/ui/FormField.svelte';
  import Input from '#lib/components/ui/Input.svelte';

  type SignInStage =
    | { name: 'password' }
    | { name: 'totp' | 'recovery-code'; flow: AuthFlow }
    | { name: 'recovery-confirm'; flow: Provisioning }
    | { name: 'recovery-complete'; recoveryCode: string };

  let stage = $state<SignInStage>({ name: 'password' });
  let email = $state('');
  let password = $state('');
  let code = $state('');
  let recoveryCode = $state('');
  let error = $state('');
  let submitting = $state(false);

  onMount(() => {
    let active = true;
    void ensureSessionLoaded().then((current) => {
      if (active && stage.name === 'password' && !submitting && current.authenticated) {
        void goto('/dashboard');
      }
    });
    return () => { active = false; };
  });

  $effect(() => {
    if (!('flow' in stage) || submitting) return;

    const timer = window.setTimeout(
      expireFlow,
      Math.max(0, Date.parse(stage.flow.expiresAt) - Date.now())
    );
    return () => window.clearTimeout(timer);
  });

  function startOver(message = '') {
    stage = { name: 'password' };
    password = '';
    code = '';
    recoveryCode = '';
    error = message;
  }

  function expireFlow() {
    startOver('This sign-in request has expired. Enter your password to start again.');
  }

  function showRecoveryOption() {
    if (stage.name !== 'totp') return;

    code = '';
    error = '';
    stage = { name: 'recovery-code', flow: stage.flow };
  }

  function returnToAuthenticator() {
    if (stage.name !== 'recovery-code') return;

    recoveryCode = '';
    error = '';
    stage = { name: 'totp', flow: stage.flow };
  }

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    if (submitting) return;

    error = '';
    submitting = true;
    try {
      switch (stage.name) {
        case 'password': {
          const result = await signIn(email, password);
          password = '';
          if (result.authenticated === true) {
            await loadSession();
            await goto('/dashboard');
          } else if (result.next === 'totp_required') {
            stage = { name: 'totp', flow: result };
          } else {
            error = 'Sign-in could not be completed.';
          }
          break;
        }
        case 'totp': {
          const result = await verifySignIn(stage.flow.flowId, code);
          if (result.authenticated === true) {
            startOver();
            await loadSession();
            await goto('/dashboard');
          } else {
            error = 'Sign-in could not be completed.';
          }
          break;
        }
        case 'recovery-code': {
          const flow = await beginRecovery(stage.flow.flowId, recoveryCode.trim());
          recoveryCode = '';
          stage = { name: 'recovery-confirm', flow };
          break;
        }
        case 'recovery-confirm': {
          const result = await confirmRecovery(stage.flow.flowId, code);
          code = '';
          clearSession();
          stage = { name: 'recovery-complete', recoveryCode: result.recoveryCode };
          break;
        }
      }
    } catch (caught) {
      if (authErrorCode(caught) === 'flow_unavailable') {
        startOver('This sign-in request is no longer available. Enter your password to start again.');
      } else {
        error = caught instanceof ApiError ? caught.message : 'Sign-in failed. Please try again.';
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
    {#if stage.name === 'password'}
      <p class="text-sm text-muted-foreground">Use the email and password for your Pyroshape account.</p>
    {:else if stage.name === 'totp'}
      <p class="text-sm text-muted-foreground">Enter the current code from your authenticator app.</p>
    {:else if stage.name === 'recovery-code'}
      <p class="text-sm text-muted-foreground">Enter the recovery code you saved to set up a replacement authenticator.</p>
    {/if}
  </div>

  <Card class="p-5">
    {#if stage.name === 'recovery-complete'}
      <SaveRecoveryCode code={stage.recoveryCode} oncontinue={() => startOver()} />
    {:else}
      <form class="grid gap-4" onsubmit={submit}>
        {#if stage.name === 'password'}
          <FormField label="Email">
            <Input bind:value={email} type="email" autocomplete="email" required />
          </FormField>
          <FormField label="Password">
            <Input bind:value={password} type="password" autocomplete="current-password" required />
          </FormField>
        {:else if stage.name === 'totp'}
          <AuthenticatorCode bind:value={code} />
        {:else if stage.name === 'recovery-code'}
          <FormField label="Recovery code">
            <Input bind:value={recoveryCode} type="text" autocomplete="off" spellcheck={false} required />
          </FormField>
        {:else if stage.name === 'recovery-confirm'}
          <h2 class="text-lg font-semibold">Set up your replacement authenticator</h2>
          <AuthenticatorSetup provisioningUri={stage.flow.provisioningUri} manualKey={stage.flow.manualKey} />
          <AuthenticatorCode label="Code from the replacement authenticator" bind:value={code} />
        {/if}

        <ErrorMessage message={error} />

        <Button type="submit" class="w-full" disabled={submitting}>
          {#if submitting}Please wait...
          {:else if stage.name === 'recovery-code'}Continue
          {:else if stage.name === 'recovery-confirm'}Confirm replacement
          {:else}Sign in{/if}
        </Button>

        {#if stage.name === 'totp'}
          <Button type="button" variant="secondary" disabled={submitting} onclick={showRecoveryOption}>Use a recovery code</Button>
        {:else if stage.name === 'recovery-code'}
          <Button type="button" variant="secondary" disabled={submitting} onclick={returnToAuthenticator}>Use an authenticator code</Button>
        {/if}
        {#if stage.name !== 'password'}
          <Button type="button" variant="ghost" disabled={submitting} onclick={() => startOver()}>Back to password</Button>
        {/if}
      </form>
    {/if}
  </Card>

  {#if stage.name === 'password'}
    <p class="text-center text-sm text-muted-foreground">
      Need an account?
      <a class="font-medium text-primary hover:underline" href="/register">Register</a>
    </p>
  {/if}
</div>
