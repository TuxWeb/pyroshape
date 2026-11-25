<script lang="ts">
  import { onMount } from 'svelte';
  import { ensureSessionLoaded, session, sessionLoading } from '#lib/session.js';
  import Card from '#lib/components/ui/Card.svelte';
  import EmptyState from '#lib/components/ui/EmptyState.svelte';
  import LoadingState from '#lib/components/ui/LoadingState.svelte';

  onMount(() => {
    ensureSessionLoaded();
  });
</script>

<div class="grid gap-6">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="grid gap-1">
      <h1 class="text-2xl font-semibold">Dashboard</h1>
      <p class="text-sm text-muted-foreground">Account session status and profile details.</p>
    </div>
  </div>

  {#if $sessionLoading}
    <LoadingState label="Loading session..." />
  {:else if !$session.authenticated}
    <EmptyState title="Not signed in" description="Sign in to open your dashboard.">
      <a class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90" href="/login">
        Go to login
      </a>
    </EmptyState>
  {:else}
    <div class="grid gap-4 md:grid-cols-2">
      <Card class="grid gap-3 p-5">
        <h2 class="text-base font-semibold">Profile</h2>
        <dl class="grid gap-3 text-sm">
          <div class="grid gap-1">
            <dt class="text-muted-foreground">Email</dt>
            <dd class="break-words font-medium">{$session.email}</dd>
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
</div>
