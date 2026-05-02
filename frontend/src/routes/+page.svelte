<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { loadSession } from '$lib/session';
  import LoadingState from '$lib/components/ui/LoadingState.svelte';

  onMount(async () => {
    const current = await loadSession().catch(() => ({ authenticated: false }));
    await goto(current.authenticated ? '/dashboard' : '/login');
  });
</script>

<LoadingState label="Loading session..." />
