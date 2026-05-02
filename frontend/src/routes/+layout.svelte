<script lang="ts">
  import '../app.css';
  import { onMount } from 'svelte';
  import { session, loadSession } from '$lib/session';
  import AppShell from '$lib/components/layout/AppShell.svelte';

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

<AppShell>
  <slot />
</AppShell>
