<script lang="ts">
  import { browser } from '$app/env';
  import { ApiError, apiJson } from '#lib/api.ts';
  import type { Session } from '#lib/session.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Card from '#lib/components/ui/Card.svelte';
  import ErrorMessage from '#lib/components/ui/ErrorMessage.svelte';
  import FormField from '#lib/components/ui/FormField.svelte';
  import Input from '#lib/components/ui/Input.svelte';

  type RegisterResponse = Partial<Session> & {
    verificationToken?: string;
  };

  let email = '';
  let password = '';
  let nickname = '';
  let error = '';
  let message = '';
  let verificationLink = '';
  let submitting = false;

  async function submit() {
    error = '';
    message = '';
    verificationLink = '';
    submitting = true;

    try {
      const response = await apiJson<RegisterResponse>('/api/auth/register', {
        method: 'POST',
        body: JSON.stringify({
          email,
          password,
          nickname: nickname.trim() === '' ? null : nickname
        })
      });

      message = 'Registration created. Verify your email before signing in.';

      if (response.verificationToken && browser) {
        const url = new URL('/verify-email', window.location.origin);
        url.searchParams.set('token', response.verificationToken);
        verificationLink = `${url.pathname}${url.search}`;
      }
    } catch (caught) {
      error = caught instanceof ApiError ? caught.message : 'Registration failed';
    } finally {
      submitting = false;
    }
  }
</script>

<div class="mx-auto grid max-w-md gap-6">
  <div class="grid gap-2">
    <h1 class="text-2xl font-semibold">Create account</h1>
    <p class="text-sm text-muted-foreground">Register with an email address and verify it before logging in.</p>
  </div>

  <Card class="p-5">
    <form class="grid gap-4" on:submit|preventDefault={submit}>
      <FormField label="Email">
        <Input bind:value={email} type="email" autocomplete="email" required />
      </FormField>

      <FormField label="Password">
        <Input bind:value={password} type="password" autocomplete="new-password" minlength="8" required />
      </FormField>

      <FormField label="Display name">
        <Input bind:value={nickname} autocomplete="nickname" placeholder="Optional" />
      </FormField>

      <ErrorMessage message={error} />

      {#if message}
        <div class="grid gap-2 rounded-md border border-primary/25 bg-primary/10 px-3 py-2 text-sm text-primary">
          <p>{message}</p>
          {#if verificationLink}
            <a class="font-medium underline" href={verificationLink}>Open development verification link</a>
          {/if}
        </div>
      {/if}

      <Button type="submit" class="w-full" disabled={submitting}>
        {submitting ? 'Creating...' : 'Create account'}
      </Button>
    </form>
  </Card>

  <p class="text-center text-sm text-muted-foreground">
    Already registered?
    <a class="font-medium text-primary hover:underline" href="/login">Sign in</a>
  </p>
</div>
