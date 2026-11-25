<script lang="ts">
  import '../app.css';
  import { onMount } from 'svelte';
  import { ensureSessionLoaded } from '#lib/session.ts';
  import AppShell from '#lib/components/layout/AppShell.svelte';

  onMount(() => {
    ensureSessionLoaded();
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
