<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { loadSession } from '$lib/session';

  onMount(async () => {
    const current = await loadSession().catch(() => ({ authenticated: false }));
    await goto(current.authenticated ? '/dashboard' : '/login');
  });
</script>

<div class="py-12 text-center text-sm text-muted-foreground">Loading...</div>
