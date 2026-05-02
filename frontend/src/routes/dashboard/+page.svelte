<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { session, sessionLoading, loadSession, logout } from '$lib/session';
  import Button from '$lib/components/ui/Button.svelte';
  import Card from '$lib/components/ui/Card.svelte';

  let error = '';

  onMount(() => {
    loadSession().catch(() => {
      session.set({ authenticated: false });
    });
  });

  async function signOut() {
    error = '';

    try {
      await logout();
      await goto('/login');
    } catch {
      error = 'Logout failed';
    }
  }
</script>

<div class="grid gap-6">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="grid gap-1">
      <h1 class="text-2xl font-semibold">Dashboard</h1>
      <p class="text-sm text-muted-foreground">Account session status and profile details.</p>
    </div>

    {#if $session.authenticated}
      <Button variant="secondary" type="button" on:click={signOut}>Log out</Button>
    {/if}
  </div>

  {#if $sessionLoading}
    <Card class="p-5 text-sm text-muted-foreground">Loading session...</Card>
  {:else if !$session.authenticated}
    <Card class="grid gap-4 p-5">
      <div class="grid gap-1">
        <h2 class="text-base font-semibold">Not signed in</h2>
        <p class="text-sm text-muted-foreground">Sign in to open your dashboard.</p>
      </div>
      <a class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90" href="/login">
        Go to login
      </a>
    </Card>
  {:else}
    <div class="grid gap-4 md:grid-cols-2">
      <Card class="grid gap-3 p-5">
        <h2 class="text-base font-semibold">Profile</h2>
        <dl class="grid gap-3 text-sm">
          <div class="grid gap-1">
            <dt class="text-muted-foreground">Email</dt>
            <dd class="font-medium">{$session.email}</dd>
          </div>
          <div class="grid gap-1">
            <dt class="text-muted-foreground">Display name</dt>
            <dd class="font-medium">{$session.nickname || 'Not set'}</dd>
          </div>
        </dl>
      </Card>

      <Card class="grid gap-3 p-5">
        <h2 class="text-base font-semibold">Security</h2>
        <dl class="grid gap-3 text-sm">
          <div class="grid gap-1">
            <dt class="text-muted-foreground">Email verified</dt>
            <dd class="font-medium">{$session.emailVerified ? 'Yes' : 'No'}</dd>
          </div>
          <div class="grid gap-1">
            <dt class="text-muted-foreground">Account enabled</dt>
            <dd class="font-medium">{$session.enabled ? 'Yes' : 'No'}</dd>
          </div>
        </dl>
      </Card>
    </div>
  {/if}

  {#if error}
    <p class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
      {error}
    </p>
  {/if}
</div>
