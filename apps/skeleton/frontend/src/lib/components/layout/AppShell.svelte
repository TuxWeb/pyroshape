<script lang="ts">
  import { goto } from '$app/navigation';
  import { session, logout } from '#lib/session.js';
  import Button from '#lib/components/ui/Button.svelte';

  let logoutError = '';
  let loggingOut = false;

  async function signOut() {
    logoutError = '';
    loggingOut = true;

    try {
      await logout();
      await goto('/login');
    } catch {
      logoutError = 'Logout failed. Please try again.';
    } finally {
      loggingOut = false;
    }
  }
</script>

<div class="min-h-screen bg-background text-foreground">
  <header class="border-b border-border bg-white">
    <div class="mx-auto flex min-h-14 w-full max-w-6xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <a href="/" class="text-base font-semibold text-foreground">Pyroshape</a>

      <nav class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
        {#if $session.authenticated}
          <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/dashboard">Dashboard</a>
          <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/account/password">Password</a>
          <Button variant="ghost" type="button" class="ml-0 min-w-20 sm:ml-2" disabled={loggingOut} onclick={signOut}>
            {loggingOut ? 'Logging out...' : 'Logout'}
          </Button>
        {:else}
          <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/login">Login</a>
          <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/register">Register</a>
        {/if}
      </nav>
    </div>
  </header>

  <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:py-8">
    {#if logoutError}
      <div class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
        {logoutError}
      </div>
    {/if}

    <slot />
  </main>
</div>
