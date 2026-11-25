<script lang="ts">
  import '../app.css';
  import { onMount } from 'svelte';
  import type { LayoutProps } from './$types';
  import { ensureSessionLoaded } from '#lib/session.js';
  import AppShell from '#lib/components/layout/AppShell.svelte';

  let { children }: LayoutProps = $props();

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
  {@render children()}
</AppShell>
