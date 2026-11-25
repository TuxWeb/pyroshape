<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { ensureSessionLoaded } from '#lib/session.ts';
  import LoadingState from '#lib/components/ui/LoadingState.svelte';

  onMount(async () => {
    const current = await ensureSessionLoaded();
    await goto(current.authenticated ? '/projects' : '/login');
  });
</script>

<LoadingState label="Loading session..." />
