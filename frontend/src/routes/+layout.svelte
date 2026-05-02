<script lang="ts">
  import '../app.css';
  import { onMount } from 'svelte';
  import { session, loadSession } from '$lib/session';

  onMount(() => {
    loadSession().catch(() => {
      session.set({ authenticated: false });
    });
  });
</script>

<svelte:head>
  <title>Pyroshape</title>
  <meta
    name="description"
    content="Pyroshape account access"
  />
</svelte:head>

<div class="min-h-screen bg-background text-foreground">
  <header class="border-b border-border bg-white/90">
    <div class="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-4">
      <a href="/" class="text-base font-semibold">Pyroshape</a>
      <nav class="flex items-center gap-1 text-sm text-muted-foreground">
        {#if $session.authenticated}
          <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/dashboard">Dashboard</a>
        {:else}
          <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/login">Login</a>
          <a class="rounded-md px-3 py-2 hover:bg-muted hover:text-foreground" href="/register">Register</a>
        {/if}
      </nav>
    </div>
  </header>

  <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:py-12">
    <slot />
  </main>
</div>
