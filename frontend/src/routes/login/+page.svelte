<script lang="ts">
  import { goto } from '$app/navigation';
  import { ApiError, apiJson } from '$lib/api';
  import { loadSession, type Session } from '$lib/session';
  import Button from '$lib/components/ui/Button.svelte';
  import Card from '$lib/components/ui/Card.svelte';
  import Input from '$lib/components/ui/Input.svelte';

  let email = '';
  let password = '';
  let error = '';
  let submitting = false;

  async function submit() {
    error = '';
    submitting = true;

    try {
      await apiJson<Session>('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password })
      });
      await loadSession();
      await goto('/dashboard');
    } catch (caught) {
      error = caught instanceof ApiError ? caught.message : 'Login failed';
    } finally {
      submitting = false;
    }
  }
</script>

<div class="mx-auto grid max-w-md gap-6">
  <div class="grid gap-2">
    <h1 class="text-2xl font-semibold">Sign in</h1>
    <p class="text-sm text-muted-foreground">Use the email and password for your Pyroshape account.</p>
  </div>

  <Card class="p-5">
    <form class="grid gap-4" on:submit|preventDefault={submit}>
      <label class="grid gap-2 text-sm font-medium">
        Email
        <Input bind:value={email} type="email" autocomplete="email" required />
      </label>

      <label class="grid gap-2 text-sm font-medium">
        Password
        <Input bind:value={password} type="password" autocomplete="current-password" required />
      </label>

      {#if error}
        <p class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </p>
      {/if}

      <Button type="submit" class="w-full" disabled={submitting}>
        {submitting ? 'Signing in...' : 'Sign in'}
      </Button>
    </form>
  </Card>

  <p class="text-center text-sm text-muted-foreground">
    Need an account?
    <a class="font-medium text-primary hover:underline" href="/register">Register</a>
  </p>
</div>
